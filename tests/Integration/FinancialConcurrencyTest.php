<?php

namespace Tests\Integration;

use App\Modules\Booking\Application\BookingService;
use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Identity\Domain\BookingActor;
use App\Modules\Payments\Application\PaymentService;
use App\Modules\Payments\Domain\PaymentGatewayInterface;
use App\Modules\Wallet\Application\RefundService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\BookingFixtures;
use Tests\Support\FakePaymentGateway;
use Tests\TestCase;

final class FinancialConcurrencyTest extends TestCase
{
    use DatabaseMigrations;
    use BookingFixtures;
    private $owner;
    private $service;
    private $actor;
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('mysql', DB::getDriverName());
        $this->seed();
        $this->owner = $this->booker();
        $this->actor = $this->bookingActor($this->owner);
        $this->service = $this->bookingServiceFixture($this->owner);
        $this->app->instance(PaymentGatewayInterface::class, new FakePaymentGateway());
        config(['payments.zarinpal' => ['enabled' => true, 'merchant_id' => '00000000-0000-4000-8000-000000000000', 'sandbox' => true, 'callback_url' => 'https://clinic.example/payments/zarinpal/callback']]);
    }
    private function hold(?string $start = null): Appointment
    {
        return app(BookingService::class)->create($this->actor, $this->bookingData($this->service, $start));
    }
    private function initiate(Appointment $appointment)
    {
        return app(PaymentService::class)->initiate($this->actor, $appointment, ['idempotency_key' => (string) Str::uuid(), 'credit_irr' => 0, 'expected_version' => 1]);
    }
    private function job(string $mode, ?Appointment $appointment = null, array $data = [], array $extra = []): array
    {
        return ['user' => $this->owner->id, 'mode' => $mode, 'data' => $data, ...($appointment ? ['appointment' => $appointment->reference] : []), ...$extra];
    }
    private function credit(): void
    {
        $appointment = $this->hold();
        $attempt = $this->initiate($appointment);
        app(PaymentService::class)->callback($attempt->reference, $attempt->authority, 'OK');
        app(BookingService::class)->change($this->actor, $appointment->fresh(), ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'reason' => 'لغو'], 'cancel');
    }
    public function test_parallel_identical_callbacks_observe_real_lock_wait_and_confirm_once(): void
    {
        $appointment = $this->hold();
        $attempt = $this->initiate($appointment);
        $job = $this->job('callback', null, [], ['attempt' => $attempt->reference]);
        $out = $this->race([$job, $job], $appointment->local_date);
        $this->assertSame(2, substr_count($out, 'SUCCESS'));
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1, DB::table('appointment_events')->where('action', 'confirmed')->count());
        $this->assertSame('confirmed', $appointment->fresh()->status);
    }
    public function test_callback_racing_cancellation_never_loses_money_or_restores_cancelled_slot(): void
    {
        $appointment = $this->hold();
        $attempt = $this->initiate($appointment);
        $out = $this->race([$this->job('callback', null, [], ['attempt' => $attempt->reference]), $this->job('cancel', $appointment, ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'reason' => 'لغو'])]);
        $this->assertGreaterThanOrEqual(1, substr_count($out, 'SUCCESS'));
        $this->assertDatabaseCount('payments', 1);
        $current = $appointment->fresh();
        $this->assertContains($current->status, ['confirmed', 'cancelled']);
        $balance = (int) DB::table('wallet_accounts')->where('owner_hash', $this->actor->mobileHash)->value('balance_irr');
        $this->assertSame($current->status === 'cancelled' ? 1000000 : 0, $balance);
    }
    public function test_expiry_and_callback_concurrently_release_reserved_credit_and_credit_bank_part_once(): void
    {
        $this->credit();
        $appointment = $this->hold();
        $attempt = app(PaymentService::class)->initiate($this->actor, $appointment, ['idempotency_key' => (string) Str::uuid(), 'credit_irr' => 400000, 'expected_version' => 1]);
        $appointment->update(['hold_expires_at' => now()->subSecond()]);
        $attempt->update(['hold_expires_at' => now()->subSecond()]);
        $out = $this->race([$this->job('callback', null, [], ['attempt' => $attempt->reference]), $this->job('expire', $appointment)]);
        $this->assertSame(2, substr_count($out, 'SUCCESS'));
        $this->assertSame('expired', $appointment->fresh()->status);
        $this->assertDatabaseHas('wallet_accounts', ['balance_irr' => 1600000, 'reserved_irr' => 0]);
        $this->assertSame(1, DB::table('wallet_entries')->where('kind', 'unallocated_payment')->count());
    }
    public function test_two_credit_payments_on_different_days_cannot_spend_same_balance(): void
    {
        $this->credit();
        $a = $this->hold();
        $b = $this->hold($a->start_at->addDay()->toIso8601String());
        $data = ['credit_irr' => 1000000, 'expected_version' => 1];
        $out = $this->race([$this->job('initiate', $a, [...$data, 'idempotency_key' => (string) Str::uuid()]), $this->job('initiate', $b, [...$data, 'idempotency_key' => (string) Str::uuid()])]);
        $this->assertSame(1, substr_count($out, 'SUCCESS'));
        $this->assertSame(1, substr_count($out, 'INSUFFICIENT_CREDIT'));
        $this->assertSame(1, Appointment::where('status', 'confirmed')->count());
        $this->assertDatabaseHas('wallet_accounts', ['balance_irr' => 0, 'reserved_irr' => 0]);
    }
    public function test_refund_and_credit_payment_compete_for_one_real_balance(): void
    {
        $this->credit();
        $appointment = $this->hold();
        $out = $this->race([$this->job('initiate', $appointment, ['credit_irr' => 1000000, 'expected_version' => 1, 'idempotency_key' => (string) Str::uuid()]), $this->job('refund', null, ['amount_irr' => 1000000, 'reason' => 'استرداد', 'idempotency_key' => (string) Str::uuid()])]);
        $this->assertSame(1, substr_count($out, 'SUCCESS'));
        $this->assertSame(1, substr_count($out, 'INSUFFICIENT_CREDIT'));
        $this->assertSame(1, DB::table('refunds')->count() + DB::table('wallet_entries')->where('kind', 'booking_payment')->count());
        $this->assertDatabaseHas('wallet_accounts', ['balance_irr' => 0, 'reserved_irr' => 0]);
    }
    public function test_parallel_duplicate_refund_creates_one_request_and_debit(): void
    {
        $this->credit();
        $job = $this->job('refund', null, ['amount_irr' => 1000000, 'reason' => 'استرداد', 'idempotency_key' => (string) Str::uuid()]);
        $out = $this->race([$job, $job]);
        $this->assertSame(2, substr_count($out, 'SUCCESS'));
        $this->assertDatabaseCount('refunds', 1);
        $this->assertSame(1, DB::table('wallet_entries')->where('kind', 'refund_reserved')->count());
        $this->assertDatabaseHas('wallet_accounts', ['balance_irr' => 0]);
    }
    public function test_two_distinct_refunds_cannot_overdraw_wallet(): void
    {
        $this->credit();
        $data = ['amount_irr' => 800000, 'reason' => 'استرداد'];
        $out = $this->race([$this->job('refund', null, [...$data, 'idempotency_key' => (string) Str::uuid()]), $this->job('refund', null, [...$data, 'idempotency_key' => (string) Str::uuid()])]);
        $this->assertSame(1, substr_count($out, 'SUCCESS'));
        $this->assertSame(1, substr_count($out, 'INSUFFICIENT_CREDIT'));
        $this->assertDatabaseCount('refunds', 1);
        $this->assertDatabaseHas('wallet_accounts', ['balance_irr' => 200000]);
    }
    public function test_parallel_rejection_and_bank_completion_have_one_final_financial_effect(): void
    {
        $this->credit();
        $refund = app(RefundService::class)->request($this->actor, 1000000, (string) Str::uuid(), 'استرداد');
        $admin = $this->booker('09123456786', 'admin');
        $actor = new BookingActor($admin->id, $admin->mobile, ['refunds.manage'], (string) config('app.key'));
        app(RefundService::class)->decide($actor, $refund->reference, 'processing', 'بررسی', null);
        $a = $this->job('decision', null, ['decision' => 'completed', 'reason' => 'سند مصنوعی', 'bank_reference' => 'TEST-REF-1'], ['refund' => $refund->reference, 'user' => $admin->id]);
        $b = $this->job('decision', null, ['decision' => 'rejected', 'reason' => 'رد'], ['refund' => $refund->reference, 'user' => $admin->id]);
        $out = $this->race([$a, $b]);
        $this->assertSame(1, substr_count($out, 'SUCCESS'));
        $this->assertSame(1, substr_count($out, 'INVALID_STATE'));
        $status = DB::table('refunds')->where('id', $refund->id)->value('status');
        $this->assertDatabaseHas('wallet_accounts', ['balance_irr' => $status === 'rejected' ? 1000000 : 0]);
    }
    public function test_parallel_clinic_receipts_only_settle_debt_once(): void
    {
        $appointment = $this->hold();
        $appointment->update(['status' => 'confirmed']); // synthetic emergency debt fixture, no actual receipt
        $secretary = $this->booker('09123456787', 'secretary');
        $data = ['amount_irr' => 1000000, 'reason' => 'رسید مصنوعی'];
        $out = $this->race([$this->job('collect', $appointment, [...$data, 'receipt' => (string) Str::uuid()], ['user' => $secretary->id]), $this->job('collect', $appointment, [...$data, 'receipt' => (string) Str::uuid()], ['user' => $secretary->id])]);
        $this->assertSame(1, substr_count($out, 'SUCCESS'));
        $this->assertSame(1, substr_count($out, 'INVALID_STATE'));
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1, DB::table('financial_events')->where('action', 'payment.clinic')->count());
        $this->assertSame(0, $appointment->fresh()->balance_due_irr);
    }
    public function test_parallel_initiation_with_same_key_creates_one_bank_attempt(): void
    {
        $appointment = $this->hold();
        $job = $this->job('initiate', $appointment, ['credit_irr' => 0, 'expected_version' => 1, 'idempotency_key' => (string) Str::uuid()]);
        $out = $this->race([$job, $job]);
        $this->assertSame(2, substr_count($out, 'SUCCESS'));
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertDatabaseHas('payment_attempts', ['state' => 'redirected']);
        $this->assertDatabaseCount('payments', 0);
    }
    public function test_parallel_paid_cancellations_only_credit_one_complete_receipt(): void
    {
        $appointment = $this->hold();
        $attempt = $this->initiate($appointment);
        app(PaymentService::class)->callback($attempt->reference, $attempt->authority, 'OK');
        $appointment->refresh();
        $data = ['expected_version' => $appointment->version, 'reason' => 'لغو'];
        $out = $this->race([$this->job('cancel', $appointment, [...$data, 'idempotency_key' => (string) Str::uuid()]), $this->job('cancel', $appointment, [...$data, 'idempotency_key' => (string) Str::uuid()])]);
        $this->assertSame(1, substr_count($out, 'SUCCESS'));
        $this->assertSame(1, substr_count($out, 'STALE_APPOINTMENT'));
        $this->assertSame(1, DB::table('wallet_entries')->where('kind', 'cancellation')->count());
        $this->assertDatabaseHas('wallet_accounts', ['balance_irr' => 1000000, 'reserved_irr' => 0]);
    }
    private function race(array $jobs, ?string $blockedDate = null): string
    {
        $barrier = storage_path('framework/finance-race-'.bin2hex(random_bytes(8)));
        $workers = [];
        try {
            if ($blockedDate) {
                DB::beginTransaction();
                DB::table('booking_day_locks')->where('date', $blockedDate)->lockForUpdate()->first();
            }
            foreach ($jobs as $job) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/financial-worker.php')], base_path(), [
                    'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mysql',
                    'DB_HOST' => config('database.connections.mysql.host'), 'DB_PORT' => (string) config('database.connections.mysql.port'),
                    'DB_DATABASE' => config('database.connections.mysql.database'), 'DB_USERNAME' => config('database.connections.mysql.username'),
                    'DB_PASSWORD' => config('database.connections.mysql.password'),
                ]);
                $worker->setInput(json_encode([...$job, 'barrier' => $barrier], JSON_THROW_ON_ERROR));
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
                $this->assertGreaterThanOrEqual(2, $waiting, 'Both callback transactions must reach a real InnoDB wait.');
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
