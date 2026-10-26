<?php

namespace Tests\Feature;

use App\Modules\Booking\Application\BookingService;
use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Payments\Infrastructure\LocalSimulatorGateway;
use App\Modules\Payments\Infrastructure\PaymentAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\Support\BookingFixtures;
use Tests\Support\FakePaymentGateway;
use Tests\TestCase;

final class LocalPaymentSimulatorTest extends TestCase
{
    use RefreshDatabase;
    use BookingFixtures;

    private $owner;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->owner = $this->booker();
        $this->service = $this->bookingServiceFixture($this->owner);
        $this->actingAs($this->owner);
        $this->app->instance('env', 'local');
        config(['app.url' => 'http://localhost', 'payments.local_simulator' => true, 'payments.zarinpal.enabled' => false]);
        URL::forceRootUrl('http://localhost');
        URL::forceScheme('http');
        $token = str_repeat('b', 40);
        $this->withSession(['_token' => $token])->withHeaders(['X-CSRF-TOKEN' => $token]);
        Http::preventStrayRequests();
    }

    private function hold(): Appointment
    {
        return app(BookingService::class)->create($this->bookingActor($this->owner), $this->bookingData($this->service));
    }

    private function begin(Appointment $appointment): PaymentAttempt
    {
        $this->post('/payments/'.$appointment->reference.'/initiate', [
            'idempotency_key' => (string) Str::uuid(), 'expected_version' => $appointment->version, 'credit_irr' => 0,
        ])->assertStatus(303);
        return PaymentAttempt::where('appointment_id', $appointment->id)->latest('id')->firstOrFail();
    }

    private function checkout(PaymentAttempt $attempt): string
    {
        return '/payments/local-simulator/'.$attempt->authority;
    }

    private function decision(PaymentAttempt $attempt, string $outcome = 'success'): string
    {
        return $this->post($this->checkout($attempt), ['outcome' => $outcome, 'amount_irr' => 1, 'callback' => 'https://evil.invalid'])
            ->assertStatus(303)->headers->get('Location');
    }

    public function test_success_runs_real_settlement_once_without_bank_network_and_labels_report(): void
    {
        $appointment = $this->hold();
        $attempt = $this->begin($appointment);
        $this->assertSame('local_simulator', $attempt->provider);
        $this->get($this->checkout($attempt))->assertOk()->assertSee('1,000,000')->assertSee('هیچ پولی جابه‌جا نمی‌شود');
        $callback = $this->decision($attempt);
        $this->get($callback)->assertOk();
        $this->get($callback)->assertOk();
        $this->get($this->decision($attempt))->assertOk();
        $this->assertSame('confirmed', $appointment->fresh()->status);
        $this->assertSame('paid', $appointment->fresh()->payment_status);
        $this->assertSame('settled', $attempt->fresh()->state);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', ['provider' => 'local_simulator', 'amount_irr' => 1000000, 'allocated' => true]);
        $admin = $this->booker('09120000001', 'admin');
        $this->actingAs($admin)->get('/management/reports?kind=finance')->assertOk()
            ->assertSee('دیتابیس شامل پرداخت شبیه‌سازی لوکال')->assertSee('آزمایشی لوکال؛ بدون وجه واقعی');
        Http::assertNothingSent();
    }

    public function test_failure_cannot_be_changed_to_success_and_can_be_retried_as_new_attempt(): void
    {
        $appointment = $this->hold();
        $attempt = $this->begin($appointment);
        $failed = $this->decision($attempt, 'failure');
        $this->get($failed)->assertOk();
        $this->assertSame($failed, $this->decision($attempt, 'success'));
        $this->get(str_replace('Status=NOK', 'Status=OK', $failed))->assertOk();
        $this->assertSame('failed', $attempt->fresh()->state);
        $this->assertSame('pending_payment', $appointment->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
        $next = $this->begin($appointment->fresh());
        $this->get($this->decision($next))->assertOk();
        $this->assertSame('confirmed', $appointment->fresh()->status);
    }

    public function test_forged_ok_without_simulator_decision_is_not_payment(): void
    {
        $appointment = $this->hold();
        $attempt = $this->begin($appointment);
        $this->get('/payments/zarinpal/callback/'.$attempt->reference.'?Authority='.$attempt->authority.'&Status=OK')->assertOk();
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('uncertain', $attempt->fresh()->state);
        $this->assertSame('pending_payment', $appointment->fresh()->status);
        $this->get($this->decision($attempt))->assertOk();
        $this->assertSame('confirmed', $appointment->fresh()->status);
    }

    public function test_late_success_credits_once_without_confirming_expired_hold(): void
    {
        $appointment = $this->hold();
        $attempt = $this->begin($appointment);
        $callback = $this->decision($attempt);
        $this->travel(16)->minutes();
        $this->get($callback)->assertOk();
        $this->get($callback)->assertOk();
        $this->assertSame('credited', $attempt->fresh()->state);
        $this->assertSame('expired', $appointment->fresh()->effectiveStatus());
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('wallet_accounts', ['balance_irr' => 1000000]);
        $this->assertDatabaseCount('payment_reconciliations', 1);
    }

    public function test_cancel_paid_appointment_restores_simulated_credit_once(): void
    {
        $appointment = $this->hold();
        $this->get($this->decision($this->begin($appointment)))->assertOk();
        $appointment->refresh();
        $payload = ['expected_version' => $appointment->version, 'idempotency_key' => (string) Str::uuid(), 'reason' => 'لغو آزمایشی'];
        $this->postJson('/api/v1/appointments/'.$appointment->reference.'/cancel', $payload)->assertOk();
        $this->postJson('/api/v1/appointments/'.$appointment->reference.'/cancel', $payload)->assertOk();
        $this->assertDatabaseHas('wallet_accounts', ['balance_irr' => 1000000]);
        Http::assertNothingSent();
    }

    public function test_other_session_cannot_view_or_decide_and_csrf_is_required(): void
    {
        $attempt = $this->begin($this->hold());
        $this->withHeaders(['X-CSRF-TOKEN' => 'wrong'])->post($this->checkout($attempt), ['outcome' => 'success'])->assertStatus(419);
        session()->forget('local_payment_owner');
        $this->get($this->checkout($attempt))->assertNotFound();
        $this->withHeaders(['X-CSRF-TOKEN' => session()->token()])->post($this->checkout($attempt), ['outcome' => 'success'])->assertNotFound();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_disabled_mode_blocks_checkout_and_never_verifies_simulated_as_real(): void
    {
        $appointment = $this->hold();
        $attempt = $this->begin($appointment);
        $callback = $this->decision($attempt);
        config(['payments.local_simulator' => false]);
        $this->get($this->checkout($attempt))->assertNotFound();
        $this->get($callback)->assertOk();
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('uncertain', $attempt->fresh()->state);
        Http::assertNothingSent();
    }

    public function test_production_blocks_simulation_even_when_flag_is_true(): void
    {
        $appointment = $this->hold();
        $attempt = $this->begin($appointment);
        $callback = $this->decision($attempt);
        $this->app->instance('env', 'production');
        config(['app.url' => 'https://localhost', 'session.secure' => true, 'session.encrypt' => true, 'session.http_only' => true, 'session.same_site' => 'lax']);
        URL::forceRootUrl('https://localhost');
        URL::forceScheme('https');
        $this->get('https://localhost'.$this->checkout($attempt))->assertNotFound();
        $this->get(str_replace('http://', 'https://', $callback))->assertOk();
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }

    public function test_remote_peer_with_spoofed_forwarding_cannot_enable_simulation(): void
    {
        $attempt = $this->begin($this->hold());
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->withHeaders(['X-Forwarded-For' => '127.0.0.1']);
        $this->get($this->checkout($attempt))->assertNotFound();
        $this->assertFalse(app(\App\Modules\Payments\Application\LocalPaymentMode::class)->enabled());
    }

    public function test_missing_cache_or_wrong_amount_is_never_proof_of_payment(): void
    {
        $appointment = $this->hold();
        $attempt = $this->begin($appointment);
        $callback = $this->decision($attempt);
        $this->assertFalse(app(LocalSimulatorGateway::class)->verifyPayment(1, $attempt->authority, 'local-simulator', true)->paid);
        Cache::forget('local-payment:'.$attempt->authority);
        $this->get($callback)->assertOk();
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('uncertain', $attempt->fresh()->state);
    }

    public function test_turning_local_mode_on_does_not_change_an_existing_real_gateway_attempt(): void
    {
        $gateway = new FakePaymentGateway();
        $this->app->instance(\App\Modules\Payments\Domain\PaymentGatewayInterface::class, $gateway);
        config(['payments.local_simulator' => false, 'payments.zarinpal' => [
            'enabled' => true, 'merchant_id' => '00000000-0000-4000-8000-000000000000',
            'sandbox' => true, 'callback_url' => 'https://clinic.example/payments/zarinpal/callback',
        ]]);
        $appointment = $this->hold();
        $attempt = $this->begin($appointment);
        config(['payments.local_simulator' => true]);
        $this->get('/payments/zarinpal/callback/'.$attempt->reference.'?Authority='.$attempt->authority.'&Status=OK')->assertOk();
        $this->assertSame('zarinpal', $attempt->provider);
        $this->assertCount(1, $gateway->verifications);
        $this->assertDatabaseHas('payments', ['provider' => 'zarinpal']);
    }

    public function test_public_origin_and_staging_never_enable_simulator(): void
    {
        config(['app.url' => 'https://clinic.example']);
        $this->assertFalse(app(\App\Modules\Payments\Application\LocalPaymentMode::class)->enabled());
        config(['app.url' => 'http://localhost']);
        $this->app->instance('env', 'staging');
        $this->assertFalse(app(\App\Modules\Payments\Application\LocalPaymentMode::class)->enabled());
        $this->get('/payments/local-simulator/SLOCAL'.str_repeat('a', 30))->assertNotFound();
    }

    public function test_invalid_outcome_is_rejected_without_recording_payment(): void
    {
        $attempt = $this->begin($this->hold());
        $this->postJson($this->checkout($attempt), ['outcome' => 'paid'])->assertUnprocessable();
        $this->get($this->checkout($attempt))->assertOk();
        $this->assertDatabaseCount('payments', 0);
    }
}
