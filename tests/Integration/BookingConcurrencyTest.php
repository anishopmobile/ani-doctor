<?php

namespace Tests\Integration;

use App\Modules\Booking\Application\BookingService;
use App\Modules\Booking\Infrastructure\Appointment;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

final class BookingConcurrencyTest extends TestCase
{
    use DatabaseMigrations;
    use BookingFixtures;
    private $a;
    private $b;
    private $service;
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mysql', DB::getDriverName());
        $this->seed();
        $this->a = $this->booker();
        $this->b = $this->booker('09123456788');
        $this->service = $this->bookingServiceFixture($this->a);
    }
    private function job($user, array $data, string $mode = 'create', ?Appointment $appointment = null): array
    {
        return ['user' => $user->id,'mode' => $mode,'data' => $data,...($appointment ? ['reference' => $appointment->reference] : [])];
    }
    public function test_two_processes_competing_for_last_capacity_only_allocate_once(): void
    {
        $data = $this->bookingData($this->service);
        $date = \Carbon\CarbonImmutable::parse($data['start_at'])->setTimezone('Asia/Tehran')->toDateString();
        $out = $this->race([$this->job($this->a, $data),$this->job($this->b, [...$data,'idempotency_key' => (string)Str::uuid()])], [$date]);
        $this->assertSame(1, substr_count($out, 'SUCCESS'));
        $this->assertSame(1, substr_count($out, 'SLOT_UNAVAILABLE'));
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('patients', 1);
    }
    public function test_parallel_same_key_replays_one_allocation(): void
    {
        $data = $this->bookingData($this->service);
        $out = $this->race([$this->job($this->a, $data),$this->job($this->a, $data)]);
        $this->assertSame(2, substr_count($out, 'SUCCESS'));
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('appointment_events', 1);
        $this->assertDatabaseCount('booking_operations', 1);
    }
    public function test_parallel_moves_to_one_target_preserve_losing_original(): void
    {
        $a = app(BookingService::class)->create($this->bookingActor($this->a), $this->bookingData($this->service));
        $b = app(BookingService::class)->create($this->bookingActor($this->b), $this->bookingData($this->service, $a->start_at->addDay()->toIso8601String()));
        $target = $a->start_at->addDays(2)->toIso8601String();
        $payload = ['expected_version' => 1,'start_at' => $target,'reason' => 'تغییر آزمایشی'];
        $out = $this->race([$this->job($this->a, [...$payload,'idempotency_key' => (string)Str::uuid()], 'reschedule', $a),$this->job($this->b, [...$payload,'idempotency_key' => (string)Str::uuid()], 'reschedule', $b)]);
        $this->assertSame(1, substr_count($out, 'SUCCESS'));
        $this->assertSame(1, substr_count($out, 'SLOT_UNAVAILABLE'));
        $this->assertSame(1, Appointment::where('version', 1)->count());
        $this->assertSame(1, Appointment::where('version', 2)->count());
        $this->assertSame(2, Appointment::where('status', 'pending_payment')->count());
    }
    public function test_patient_limit_is_atomic_across_operators_and_different_days(): void
    {
        $first = app(BookingService::class)->create($this->bookingActor($this->a), $this->bookingData($this->service));
        $first->update(['status' => 'cancelled']);
        DB::table('booking_settings')->where('id', 1)->update(['max_active_patient' => 1,'max_active_booker' => 10]);
        $this->a->roles()->sync([\App\Modules\Identity\Infrastructure\Role::where('name', 'secretary')->sole()->id]);
        $this->b->roles()->sync([\App\Modules\Identity\Infrastructure\Role::where('name', 'secretary')->sole()->id]);
        $base = [...$this->bookingData($this->service),'patient_id' => $first->patient_id];
        $out = $this->race([
            $this->job($this->a, [...$base,'booker_mobile' => '09120000000']),
            $this->job($this->b, [...$base,'booker_mobile' => '09120000001','start_at' => $first->start_at->addDay()->toIso8601String(),'idempotency_key' => (string)Str::uuid()])
        ]);
        $this->assertSame(1, substr_count($out, 'SUCCESS'));
        $this->assertSame(1, substr_count($out, 'BOOKING_LIMIT_REACHED'));
        $this->assertSame(1, Appointment::where('status', 'pending_payment')->count());
    }
    public function test_booker_limit_is_atomic_across_different_patients_and_operators(): void
    {
        DB::table('booking_settings')->where('id', 1)->update(['max_active_booker' => 1]);
        $role = \App\Modules\Identity\Infrastructure\Role::where('name', 'secretary')->sole()->id;
        $this->a->roles()->sync([$role]);
        $this->b->roles()->sync([$role]);
        $base = [...$this->bookingData($this->service),'booker_mobile' => '09120000000'];
        $out = $this->race([$this->job($this->a, $base),$this->job($this->b, [...$base,'start_at' => \Carbon\CarbonImmutable::parse($base['start_at'])->addDay()->toIso8601String(),'idempotency_key' => (string)Str::uuid()])]);
        $this->assertSame(1, substr_count($out, 'SUCCESS'));
        $this->assertSame(1, substr_count($out, 'BOOKING_LIMIT_REACHED'));
        $this->assertDatabaseCount('patients', 1);
    }
    public function test_cancel_and_move_cannot_both_apply_same_version(): void
    {
        $appointment = app(BookingService::class)->create($this->bookingActor($this->a), $this->bookingData($this->service));
        $data = ['expected_version' => 1,'reason' => 'درخواست آزمایشی'];
        $out = $this->race([
            $this->job($this->a, [...$data,'idempotency_key' => (string)Str::uuid()], 'cancel', $appointment),
            $this->job($this->a, [...$data,'idempotency_key' => (string)Str::uuid(),'start_at' => $appointment->start_at->addDay()->toIso8601String()], 'reschedule', $appointment)
        ]);
        $this->assertSame(1, substr_count($out, 'SUCCESS'));
        $this->assertSame(1, substr_count($out, 'STALE_APPOINTMENT'));
        $this->assertDatabaseCount('appointment_events', 2);
        $this->assertSame(2, $appointment->fresh()->version);
    }
    public function test_expiry_and_reallocation_can_run_together_without_losing_new_hold(): void
    {
        $appointment = app(BookingService::class)->create($this->bookingActor($this->a), $this->bookingData($this->service));
        $appointment->update(['hold_expires_at' => now()->subSecond()]);
        $out = $this->race([$this->job($this->a, [], 'expire', $appointment),$this->job($this->b, $this->bookingData($this->service, $appointment->start_at->toIso8601String()))]);
        $this->assertSame(2, substr_count($out, 'SUCCESS'));
        $this->assertSame('expired', $appointment->fresh()->status);
        $this->assertSame(1, Appointment::where('status', 'pending_payment')->count());
    }
    private function race(array $jobs, array $blockedDates = []): string
    {
        $barrier = storage_path('framework/booking-race-'.bin2hex(random_bytes(8)));
        $workers = [];
        try {
            if ($blockedDates) {
                foreach ($blockedDates as $date) {
                    DB::table('booking_day_locks')->insertOrIgnore(['date' => $date]);
                }
                DB::beginTransaction();
                DB::table('booking_day_locks')->whereIn('date', $blockedDates)->lockForUpdate()->get();
            }
            foreach ($jobs as $job) {
                $worker = new Process([PHP_BINARY,base_path('tests/Support/booking-worker.php')], base_path(), [
                    'APP_ENV' => 'testing','APP_KEY' => config('app.key'),'DB_CONNECTION' => 'mysql',
                    'DB_HOST' => config('database.connections.mysql.host'),'DB_PORT' => (string)config('database.connections.mysql.port'),
                    'DB_DATABASE' => config('database.connections.mysql.database'),'DB_USERNAME' => config('database.connections.mysql.username'),
                    'DB_PASSWORD' => config('database.connections.mysql.password'),
                ]);
                $worker->setInput(json_encode([...$job,'barrier' => $barrier], JSON_THROW_ON_ERROR));
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
            if ($blockedDates) {
                // Observe both independent transactions actually waiting on the parent-held day row.
                $deadline = microtime(true) + 10;
                do {
                    $waiting = (int)DB::selectOne('SELECT COUNT(DISTINCT REQUESTING_THREAD_ID) AS waiting FROM performance_schema.data_lock_waits')->waiting;
                    if ($waiting >= 2) {
                        break;
                    }
                    usleep(20000);
                } while (microtime(true) < $deadline);
                $this->assertGreaterThanOrEqual(2, $waiting, 'Both workers must reach a real InnoDB lock wait: '.$workers[0]->getOutput().$workers[1]->getOutput().$workers[0]->getErrorOutput().$workers[1]->getErrorOutput());
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
            if ($blockedDates && DB::transactionLevel() > 0) {
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
