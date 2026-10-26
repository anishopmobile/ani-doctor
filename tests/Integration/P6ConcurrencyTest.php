<?php

namespace Tests\Integration;

use App\Modules\Booking\Application\BookingService;
use App\Modules\Booking\Application\FinancialBookingService;
use App\Modules\DynamicForms\Application\PrivateFormService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

final class P6ConcurrencyTest extends TestCase
{
    use DatabaseMigrations;
    use BookingFixtures;
    private $patient;
    private $admin;
    private $appointment;
    private int $form;
    private array $definition;
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mysql', DB::getDriverName());
        $this->seed();
        $this->admin = $this->booker('09120000001', 'admin');
        $this->patient = $this->booker();
        $service = $this->bookingServiceFixture($this->admin);
        $this->appointment = app(BookingService::class)->create($this->bookingActor($this->patient), $this->bookingData($service));
        $this->definition = ['title' => 'فرم ساختگی', 'service_id' => $service->id, 'is_active' => true, 'expected_version' => 0, 'reason' => 'تست', 'schema' => [['key' => 'note', 'label' => 'اطلاعات ضروری', 'type' => 'text', 'required' => true]]];
        $this->form = app(PrivateFormService::class)->publish($this->admin, null, $this->definition);
    }
    private function submitJob(): array
    {
        return ['mode' => 'submit', 'user' => $this->patient->id, 'data' => ['version_id' => DB::table('form_versions')->sole()->id, 'expected_submission_id' => 0, 'idempotency_key' => (string) Str::uuid(), 'consent' => true, 'answers' => ['note' => 'پاسخ ساختگی تست']]];
    }
    public function test_two_identical_submissions_replay_one_encrypted_answer(): void
    {
        $job = $this->submitJob();
        $output = $this->race([$job, $job]);
        $this->assertSame(2, substr_count($output, 'SUCCESS'));
        $this->assertDatabaseCount('form_submissions', 1);
        $this->assertDatabaseCount('form_access_logs', 1);
    }
    public function test_competing_answers_only_accept_one_revision(): void
    {
        $output = $this->race([$this->submitJob(), $this->submitJob()]);
        $this->assertSame(1, substr_count($output, 'SUCCESS'));
        $this->assertSame(1, substr_count($output, 'HTTP_409'));
        $this->assertDatabaseCount('form_submissions', 1);
    }
    public function test_competing_publishers_only_create_one_next_version(): void
    {
        $job = ['mode' => 'publish', 'user' => $this->admin->id, 'data' => [...$this->definition, 'expected_version' => 1]];
        $output = $this->race([$job, $job]);
        $this->assertSame(1, substr_count($output, 'SUCCESS'));
        $this->assertSame(1, substr_count($output, 'HTTP_409'));
        $this->assertDatabaseCount('form_versions', 2);
    }
    public function test_two_workers_call_provider_once(): void
    {
        config(['notifications.enabled' => true]);
        DB::table('sms_templates')->insert(['event' => 'confirmed', 'provider_template_id' => 123, 'enabled' => true, 'reminder_minutes' => 1440]);
        DB::transaction(fn () => app(FinancialBookingService::class)->confirm($this->appointment, 'unpaid', $this->appointment->price_irr, null, 'fixture'));
        $id = DB::table('message_outbox')->sole()->id;
        $job = ['mode' => 'send', 'user' => $this->patient->id, 'message' => $id];
        $output = $this->race([$job, $job]);
        $this->assertSame(2, substr_count($output, 'SUCCESS'));
        $this->assertSame(1, DB::table('sms_delivery_logs')->where('state', 'fixture_provider_call')->count());
        $this->assertDatabaseHas('message_outbox', ['id' => $id, 'attempts' => 1, 'state' => 'accepted']);
    }
    public function test_uncommitted_outbox_is_invisible_to_another_connection_and_rollback_sends_nothing(): void
    {
        config(['notifications.enabled' => true]);
        DB::table('sms_templates')->insert(['event' => 'confirmed', 'provider_template_id' => 123, 'enabled' => true, 'reminder_minutes' => 1440]);
        $barrier = storage_path('framework/p6-uncommitted-'.bin2hex(random_bytes(8)));
        $worker = null;
        try {
            DB::beginTransaction();
            app(FinancialBookingService::class)->confirm($this->appointment, 'unpaid', $this->appointment->price_irr, null, 'fixture');
            $id = DB::table('message_outbox')->sole()->id;
            touch($barrier);
            $worker = new Process([PHP_BINARY, base_path('tests/Support/p6-worker.php')], base_path(), ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mysql', 'DB_HOST' => config('database.connections.mysql.host'), 'DB_PORT' => (string) config('database.connections.mysql.port'), 'DB_DATABASE' => config('database.connections.mysql.database'), 'DB_USERNAME' => config('database.connections.mysql.username'), 'DB_PASSWORD' => config('database.connections.mysql.password')]);
            $worker->setInput(json_encode(['mode' => 'send', 'message' => $id, 'user' => $this->patient->id, 'appointment' => $this->appointment->id, 'now' => now()->toIso8601String(), 'barrier' => $barrier]));
            $worker->setTimeout(15)->run();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            $this->assertStringContainsString('SUCCESS', $worker->getOutput());
            $this->assertSame(0, DB::table('sms_delivery_logs')->count());
            DB::rollBack();
            $this->assertDatabaseCount('message_outbox', 0);
            $this->assertSame('pending_payment', $this->appointment->fresh()->status);
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
            if ($worker?->isRunning()) {
                $worker->stop();
            }
            if (file_exists($barrier)) {
                unlink($barrier);
            }
        }
    }
    private function race(array $jobs): string
    {
        $barrier = storage_path('framework/p6-race-'.bin2hex(random_bytes(8)));
        $workers = [];
        try {
            DB::beginTransaction();
            if ($jobs[0]['mode'] === 'submit') {
                DB::table('booking_actor_locks')->where('key', $this->bookingActor($this->patient)->key)->lockForUpdate()->first();
            } else {
                DB::table('appointments')->where('id', $this->appointment->id)->lockForUpdate()->first();
            }
            foreach ($jobs as $job) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/p6-worker.php')], base_path(), ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mysql', 'DB_HOST' => config('database.connections.mysql.host'), 'DB_PORT' => (string) config('database.connections.mysql.port'), 'DB_DATABASE' => config('database.connections.mysql.database'), 'DB_USERNAME' => config('database.connections.mysql.username'), 'DB_PASSWORD' => config('database.connections.mysql.password')]);
                $worker->setInput(json_encode([...$job, 'form' => $this->form, 'appointment' => $this->appointment->id, 'now' => now()->toIso8601String(), 'barrier' => $barrier]));
                $worker->setTimeout(30)->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 10;
            while (!str_contains($workers[0]->getOutput(), 'READY') || !str_contains($workers[1]->getOutput(), 'READY')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Workers did not become ready.');
                }
                usleep(10000);
            }
            touch($barrier);
            if ($jobs[0]['mode'] !== 'publish') {
                $deadline = microtime(true) + 10;
                do {
                    $waiting = (int) DB::selectOne('SELECT COUNT(DISTINCT REQUESTING_THREAD_ID) AS waiting FROM performance_schema.data_lock_waits')->waiting;
                    if ($waiting >= 2) {
                        break;
                    }
                    usleep(20000);
                } while (microtime(true) < $deadline);
                $this->assertGreaterThanOrEqual(2, $waiting, 'Both workers must encounter a real InnoDB lock wait.');
            }
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
            if (file_exists($barrier)) {
                unlink($barrier);
            }
        }
    }
}
