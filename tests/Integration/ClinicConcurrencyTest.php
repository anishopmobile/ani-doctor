<?php

namespace Tests\Integration;

use App\Modules\Booking\Application\AttendanceService;
use App\Modules\Booking\Application\DeskBookingService;
use App\Modules\Booking\Infrastructure\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\ClinicFixtures;
use Tests\TestCase;

final class ClinicConcurrencyTest extends TestCase
{
    use DatabaseMigrations;
    use ClinicFixtures;
    private $secretary;
    private $service;
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mysql', DB::getDriverName());
        $this->seed();
        $this->travelTo(CarbonImmutable::parse('2026-10-25 08:00', 'Asia/Tehran'));
        $this->secretary = $this->booker('09123456787', 'secretary');
        $this->service = $this->bookingServiceFixture($this->secretary);
    }
    private function job(string $mode, array $data, ?Appointment $appointment = null): array
    {
        return ['user' => $this->secretary->id, 'mode' => $mode, 'data' => $data, ...($appointment ? ['appointment' => $appointment->reference] : [])];
    }
    private function todayAppointment(array $changes = []): Appointment
    {
        return $this->clinicAppointment(['start_at' => CarbonImmutable::now('Asia/Tehran')->setTime(17, 0)->utc()->toIso8601String(), ...$changes]);
    }
    public function test_two_secretaries_recording_arrival_observe_real_lock_wait_and_one_transition(): void
    {
        $appointment = $this->todayAppointment();
        $a = $this->job('attendance', $this->attendanceData($appointment, 'arrived'), $appointment);
        $b = $this->job('attendance', $this->attendanceData($appointment, 'arrived'), $appointment);
        $b['user'] = $this->booker('09123456788', 'secretary')->id;
        $out = $this->race([$a, $b], $appointment->local_date);
        $this->assertSame(1, substr_count($out, 'SUCCESS'));
        $this->assertSame(1, substr_count($out, 'STALE_APPOINTMENT'));
        $this->assertDatabaseCount('attendance_events', 1);
        $this->assertSame('arrived', $appointment->fresh()->attendance_status);
    }
    public function test_same_attendance_key_replays_one_event_in_two_processes(): void
    {
        $appointment = $this->todayAppointment();
        $job = $this->job('attendance', $this->attendanceData($appointment, 'arrived'), $appointment);
        $out = $this->race([$job, $job]);
        $this->assertSame(2, substr_count($out, 'SUCCESS'));
        $this->assertDatabaseCount('attendance_events', 1);
        $this->assertSame(3, $appointment->fresh()->version);
    }
    public function test_arrival_racing_paid_cancellation_has_one_effect_and_no_accidental_refund(): void
    {
        $appointment = $this->todayAppointment();
        $out = $this->race([$this->job('attendance', $this->attendanceData($appointment, 'arrived'), $appointment), $this->job('cancel', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => $appointment->version, 'reason' => 'لغو'], $appointment)]);
        $this->assertSame(1, substr_count($out, 'SUCCESS'));
        $current = $appointment->fresh();
        $this->assertContains($current->status, ['confirmed', 'cancelled']);
        $this->assertSame($current->status === 'cancelled' ? 1 : 0, DB::table('wallet_entries')->where('kind', 'cancellation')->count());
        $this->assertSame($current->status === 'confirmed' ? 'arrived' : 'scheduled', $current->attendance_status);
    }
    public function test_completion_racing_arrival_reversion_only_accepts_one_version(): void
    {
        $appointment = $this->todayAppointment();
        app(AttendanceService::class)->change($this->bookingActor($this->secretary), $appointment, $this->attendanceData($appointment, 'arrived'));
        $appointment->refresh();
        $this->travelTo($appointment->start_at);
        $out = $this->race([$this->job('attendance', $this->attendanceData($appointment, 'completed'), $appointment), $this->job('attendance', $this->attendanceData($appointment, 'scheduled'), $appointment)]);
        $this->assertSame(1, substr_count($out, 'SUCCESS'));
        $this->assertSame(1, substr_count($out, 'STALE_APPOINTMENT'));
        $this->assertDatabaseCount('attendance_events', 2);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('wallet_entries', 0);
    }
    public function test_complete_and_collect_debt_can_both_succeed_without_losing_payment_or_attendance(): void
    {
        DB::table('payment_settings')->where('id', 1)->update(['emergency_until' => now()->addDay()]);
        $appointment = app(DeskBookingService::class)->create($this->bookingActor($this->secretary), $this->deskData(['settlement' => 'clinic_due', 'start_at' => CarbonImmutable::now('Asia/Tehran')->setTime(17, 0)->utc()->toIso8601String()]))->fresh();
        app(AttendanceService::class)->change($this->bookingActor($this->secretary), $appointment, $this->attendanceData($appointment, 'arrived'));
        $appointment->refresh();
        $this->travelTo($appointment->start_at);
        $out = $this->race([$this->job('attendance', $this->attendanceData($appointment, 'completed'), $appointment), $this->job('collect', ['amount_irr' => 1000000, 'receipt' => (string) Str::uuid(), 'reason' => 'دریافت آزمایشی'], $appointment)]);
        $this->assertSame(2, substr_count($out, 'SUCCESS'));
        $this->assertSame('completed', $appointment->fresh()->status);
        $this->assertSame('paid', $appointment->fresh()->payment_status);
        $this->assertSame(0, $appointment->fresh()->balance_due_irr);
        $this->assertDatabaseCount('payments', 1);
    }
    public function test_last_capacity_two_phone_bookings_only_make_one_patient_and_full_receipt(): void
    {
        $a = $this->cashData();
        $b = $this->cashData();
        $out = $this->race([$this->job('book', $a), $this->job('book', $b)]);
        $this->assertSame(1, substr_count($out, 'SUCCESS'));
        $this->assertSame(1, substr_count($out, 'SLOT_UNAVAILABLE'));
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('patients', 1);
        $this->assertDatabaseCount('payments', 1);
    }
    public function test_two_identical_phone_paid_requests_have_one_receipt_and_no_partial_hold(): void
    {
        $job = $this->job('book', $this->cashData());
        $out = $this->race([$job, $job]);
        $this->assertSame(2, substr_count($out, 'SUCCESS'));
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseHas('appointments', ['status' => 'confirmed', 'payment_status' => 'paid']);
        $this->assertDatabaseCount('payments', 1);
    }
    public function test_parallel_urgent_off_grid_overlapping_bookings_without_override_do_not_overbook(): void
    {
        $start = CarbonImmutable::now('Asia/Tehran')->setTime(8, 1)->utc()->toIso8601String();
        $a = $this->cashData(['booking_kind' => 'urgent', 'start_at' => $start]);
        $b = $this->cashData(['booking_kind' => 'urgent', 'start_at' => CarbonImmutable::parse($start)->addMinute()->toIso8601String()]);
        $out = $this->race([$this->job('book', $a), $this->job('book', $b)]);
        $this->assertSame(1, substr_count($out, 'SUCCESS'));
        $this->assertSame(1, substr_count($out, 'SLOT_UNAVAILABLE'));
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('payments', 1);
    }
    public function test_manual_hold_collection_racing_cancellation_never_creates_unbacked_credit(): void
    {
        $hold = app(DeskBookingService::class)->create($this->bookingActor($this->secretary), $this->deskData())->fresh();
        $out = $this->race([$this->job('collect_hold', ['amount_irr' => 1000000, 'receipt' => (string) Str::uuid(), 'reason' => 'دریافت ساختگی'], $hold), $this->job('cancel', ['expected_version' => 1, 'idempotency_key' => (string) Str::uuid(), 'reason' => 'لغو'], $hold)]);
        $this->assertSame(1, substr_count($out, 'SUCCESS'));
        $current = $hold->fresh();
        $this->assertContains($current->status, ['confirmed', 'cancelled']);
        $this->assertSame($current->status === 'confirmed' ? 1 : 0, DB::table('payments')->count());
        $this->assertDatabaseCount('wallet_entries', 0);
    }
    private function race(array $jobs, ?string $blockedDate = null): string
    {
        $barrier = storage_path('framework/clinic-race-'.bin2hex(random_bytes(8)));
        $workers = [];
        try {
            if ($blockedDate) {
                DB::beginTransaction();
                DB::table('booking_day_locks')->where('date', $blockedDate)->lockForUpdate()->first();
            }
            foreach ($jobs as $job) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/clinic-worker.php')], base_path(), ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mysql', 'DB_HOST' => config('database.connections.mysql.host'), 'DB_PORT' => (string) config('database.connections.mysql.port'), 'DB_DATABASE' => config('database.connections.mysql.database'), 'DB_USERNAME' => config('database.connections.mysql.username'), 'DB_PASSWORD' => config('database.connections.mysql.password')]);
                $worker->setInput(json_encode([...$job, 'now' => now()->toIso8601String(), 'barrier' => $barrier], JSON_THROW_ON_ERROR));
                $worker->setTimeout(30)->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 10;
            while (!str_contains($workers[0]->getOutput(), 'READY') || !str_contains($workers[1]->getOutput(), 'READY')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Workers not ready: '.$workers[0]->getErrorOutput().$workers[1]->getErrorOutput());
                }
                usleep(10000);
            }
            touch($barrier);
            if ($blockedDate) {
                $deadline = microtime(true) + 10;
                do {
                    $waiting = (int) DB::selectOne('SELECT COUNT(DISTINCT REQUESTING_THREAD_ID) AS waiting FROM performance_schema.data_lock_waits')->waiting;
                    if ($waiting >= 2) {
                        break;
                    }
                    usleep(20000);
                } while (microtime(true) < $deadline);
                $this->assertGreaterThanOrEqual(2, $waiting, 'Both attendance processes must reach a real InnoDB wait.');
                DB::commit();
            }
            $output = '';
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
                $output .= $worker->getOutput();
            }
            return $output;
        } finally {
            if ($blockedDate && DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            if (file_exists($barrier)) {
                unlink($barrier);
            }
        }
    }
}
