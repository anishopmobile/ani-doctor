<?php

namespace Tests\Integration;

use App\Modules\Patients\Infrastructure\Patient;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\P7Fixtures;
use Tests\TestCase;

final class P7DiscountConcurrencyTest extends TestCase
{
    use DatabaseMigrations;
    use P7Fixtures;
    private $admin;
    private $patient;
    private $secretary;
    private $service;
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mysql', DB::getDriverName());
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-26 08:00', 'Asia/Tehran'));
        $this->seed();
        $this->admin = $this->booker('09120000001', 'admin');
        $this->secretary = $this->booker('09120000002', 'secretary');
        $this->patient = $this->booker();
        $this->service = $this->bookingServiceFixture($this->admin);
    }
    private function bookJob(array $changes = []): array
    {
        return ['user' => $this->patient->id, 'mode' => 'book', 'data' => [...$this->bookingData($this->service), 'discount_code' => 'SAVE20', ...$changes]];
    }
    public function test_last_global_coupon_use_across_different_days_has_one_winner_after_real_lock_wait(): void
    {
        $id = $this->offer(['max_uses' => 1]);
        $other = $this->booker('09120000003');
        $a = $this->bookJob();
        $b = $this->bookJob(['start_at' => now('Asia/Tehran')->addDays(4)->setTime(17, 0)->utc()->toIso8601String()]);
        $b['user'] = $other->id;
        $output = $this->race([$a, $b], $id);
        $this->assertSame(1, substr_count($output, 'SUCCESS'));
        $this->assertSame(1, substr_count($output, 'DISCOUNT_UNAVAILABLE'));
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('discount_redemptions', 1);
        $this->assertDatabaseCount('patients', 1);
    }
    public function test_per_patient_limit_is_atomic_even_with_free_capacity_on_two_days(): void
    {
        $id = $this->offer(['per_patient' => 1]);
        $patient = Patient::create(['name' => 'بیمار ساختگی']);
        DB::table('patient_user_links')->insert(['patient_id' => $patient->id, 'user_id' => $this->patient->id, 'created_at' => now()]);
        $a = $this->bookJob(['patient_id' => $patient->id]);
        $b = $this->bookJob(['patient_id' => $patient->id, 'start_at' => now('Asia/Tehran')->addDays(4)->setTime(17, 0)->utc()->toIso8601String()]);
        $output = $this->race([$a, $b], $id);
        $this->assertSame(1, substr_count($output, 'SUCCESS'));
        $this->assertSame(1, substr_count($output, 'DISCOUNT_UNAVAILABLE'));
        $this->assertDatabaseCount('discount_redemptions', 1);
    }
    public function test_identical_booking_requests_reserve_only_one_discount(): void
    {
        $this->offer(['max_uses' => 1]);
        $job = $this->bookJob();
        $output = $this->race([$job, $job]);
        $this->assertSame(2, substr_count($output, 'SUCCESS'));
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('discount_redemptions', 1);
        $this->assertDatabaseCount('discount_events', 1);
    }
    public function test_expiry_racing_new_reservation_releases_only_the_old_coupon_slot(): void
    {
        $this->offer(['max_uses' => 1]);
        $held = $this->discounted();
        $this->travelTo($held->hold_expires_at);
        $output = $this->race([['user' => $this->patient->id, 'mode' => 'expire', 'appointment' => $held->id, 'data' => []], $this->bookJob()]);
        $this->assertSame(2, substr_count($output, 'SUCCESS'));
        $this->assertDatabaseHas('discount_redemptions', ['appointment_id' => $held->id, 'state' => 'released']);
        $this->assertSame(1, DB::table('discount_redemptions')->where('state', 'reserved')->count());
    }
    public function test_duplicate_cash_confirmation_consumes_discount_once_with_one_receipt(): void
    {
        $this->offer();
        $held = $this->discounted();
        $job = ['user' => $this->secretary->id, 'mode' => 'collect', 'appointment' => $held->id, 'data' => ['receipt' => (string) Str::uuid()]];
        $output = $this->race([$job, $job]);
        $this->assertSame(2, substr_count($output, 'SUCCESS'));
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', ['amount_irr' => 800000]);
        $this->assertDatabaseCount('discount_events', 2);
        $this->assertDatabaseHas('discount_redemptions', ['state' => 'consumed']);
    }
    private function race(array $jobs, ?int $blockedOffer = null): string
    {
        $barrier = storage_path('framework/p7-race-'.bin2hex(random_bytes(8)));
        $workers = [];
        try {
            if ($blockedOffer) {
                DB::beginTransaction();
                DB::table('discounts')->where('id', $blockedOffer)->lockForUpdate()->first();
            }
            foreach ($jobs as $job) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/p7-worker.php')], base_path(), ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mysql', 'DB_HOST' => config('database.connections.mysql.host'), 'DB_PORT' => (string) config('database.connections.mysql.port'), 'DB_DATABASE' => config('database.connections.mysql.database'), 'DB_USERNAME' => config('database.connections.mysql.username'), 'DB_PASSWORD' => config('database.connections.mysql.password')]);
                $worker->setInput(json_encode([...$job, 'now' => now()->toIso8601String(), 'barrier' => $barrier]));
                $worker->setTimeout(30)->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 10;
            while (!str_contains($workers[0]->getOutput(), 'READY') || !str_contains($workers[1]->getOutput(), 'READY')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Workers not ready.');
                }
                usleep(10000);
            }
            touch($barrier);
            if ($blockedOffer) {
                $deadline = microtime(true) + 10;
                do {
                    $waiting = (int) DB::selectOne('SELECT COUNT(DISTINCT REQUESTING_THREAD_ID) AS waiting FROM performance_schema.data_lock_waits')->waiting;
                    if ($waiting >= 2) {
                        break;
                    }
                    usleep(20000);
                } while (microtime(true) < $deadline);
                $this->assertGreaterThanOrEqual(2, $waiting);
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
            if ($blockedOffer && DB::transactionLevel()) {
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
