<?php

namespace Tests\Integration;

use App\Modules\MedicalRecords\Application\ClinicalService;
use App\Modules\MedicalRecords\Infrastructure\ClinicalRecord;
use App\Modules\Patients\Infrastructure\Patient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\ClinicFixtures;
use Tests\TestCase;

final class MedicalRecordConcurrencyTest extends TestCase
{
    use DatabaseMigrations;
    use ClinicFixtures;

    private $admin;
    private $secretary;
    private $service;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mysql', DB::getDriverName());
        $this->assertStringStartsWith('ani_doctor_test', config('database.connections.mysql.database'));
        $this->seed();
        $this->travelTo(CarbonImmutable::parse('2026-10-30 08:00', 'Asia/Tehran'));
        $this->admin = $this->booker('09120000441', 'admin');
        $this->secretary = $this->booker('09120000442', 'secretary');
        $this->patient = Patient::create(['name' => 'بیمار ساختگی رقابت EMR']);
    }

    protected function tearDown(): void
    {
        // Only fixtures in this explicitly isolated test DB; production rollback refuses clinical data.
        foreach (ClinicalRecord::orderByDesc('id')->get() as $record) {
            $record->delete();
        }
        parent::tearDown();
    }

    private function data(array $changes = []): array
    {
        return ['request_key' => (string) Str::uuid(), 'occurred_jalali' => '1405-08-08', 'occurred_time' => '08:00', 'payload' => ['complaint' => 'ساختگی رقابت'], ...$changes];
    }

    public function test_two_processes_replay_same_key_once_after_real_patient_lock_wait(): void
    {
        $data = $this->data();
        $output = $this->race([['data' => $data], ['data' => $data]]);
        $this->assertSame(2, substr_count($output, 'SUCCESS'));
        $this->assertDatabaseCount('emr_records', 1);
    }

    public function test_two_draft_updates_have_one_winner_and_one_version_conflict(): void
    {
        $record = app(ClinicalService::class)->save($this->admin, $this->patient, 'visit', $this->data());
        $output = $this->race([['record' => $record->id, 'data' => $this->data(['expected_version' => 1])], ['record' => $record->id, 'data' => $this->data(['expected_version' => 1])]]);
        $this->assertSame(1, substr_count($output, 'SUCCESS'));
        $this->assertSame(1, substr_count($output, 'HTTP_409'));
        $this->assertSame(2, $record->fresh()->version);
    }

    public function test_two_starts_for_one_arrived_appointment_create_one_visit(): void
    {
        $this->service = $this->bookingServiceFixture($this->secretary);
        $appointment = $this->clinicAppointment(['start_at' => now('Asia/Tehran')->setTime(9, 0)->utc()->toIso8601String()]);
        app(\App\Modules\Booking\Application\AttendanceService::class)->change($this->bookingActor($this->secretary), $appointment, $this->attendanceData($appointment, 'arrived'));
        $this->patient = $appointment->patient;
        $output = $this->race([['data' => $this->data(['appointment_id' => $appointment->id])], ['data' => $this->data(['appointment_id' => $appointment->id])]]);
        $this->assertSame(2, substr_count($output, 'SUCCESS'));
        $this->assertSame(1, ClinicalRecord::where('appointment_id', $appointment->id)->count());
    }

    private function race(array $jobs): string
    {
        $barrier = storage_path('framework/emr-race-'.bin2hex(random_bytes(8)));
        $workers = [];
        try {
            DB::beginTransaction();
            Patient::whereKey($this->patient->id)->lockForUpdate()->firstOrFail();
            foreach ($jobs as $job) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/emr-worker.php')], base_path(), ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mysql', 'DB_HOST' => config('database.connections.mysql.host'), 'DB_PORT' => (string) config('database.connections.mysql.port'), 'DB_DATABASE' => config('database.connections.mysql.database'), 'DB_USERNAME' => config('database.connections.mysql.username'), 'DB_PASSWORD' => config('database.connections.mysql.password')]);
                $worker->setInput(json_encode([...$job, 'patient' => $this->patient->id, 'actor' => $this->admin->id, 'barrier' => $barrier], JSON_THROW_ON_ERROR));
                $worker->setTimeout(30)->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 15;
            while (!str_contains($workers[0]->getOutput(), 'READY') || !str_contains($workers[1]->getOutput(), 'READY')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Workers not ready: '.$workers[0]->getErrorOutput().$workers[1]->getErrorOutput());
                }
                usleep(10000);
            }
            touch($barrier);
            $deadline = microtime(true) + 10;
            do {
                $waiting = (int) DB::selectOne('SELECT COUNT(DISTINCT REQUESTING_THREAD_ID) AS waiting FROM performance_schema.data_lock_waits')->waiting;
                if ($waiting >= 2) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            $this->assertGreaterThanOrEqual(2, $waiting, 'Both processes must reach a real InnoDB row-lock wait.');
            DB::commit();
            $output = '';
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
                $output .= $worker->getOutput();
            }
            return $output;
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            if (is_file($barrier)) {
                unlink($barrier);
            }
        }
    }
}
