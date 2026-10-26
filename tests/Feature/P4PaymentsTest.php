<?php

namespace Tests\Feature;

use App\Modules\Booking\Application\BookingService;
use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Identity\Domain\BookingActor;
use App\Modules\Payments\Application\PaymentService;
use App\Modules\Payments\Domain\PaymentGatewayInterface;
use App\Modules\Payments\Infrastructure\PaymentAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BookingFixtures;
use Tests\Support\FakePaymentGateway;
use Tests\TestCase;

final class P4PaymentsTest extends TestCase
{
    use RefreshDatabase;
    use BookingFixtures;
    private $owner;
    private $service;
    private FakePaymentGateway $gateway;
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->owner = $this->booker();
        $this->service = $this->bookingServiceFixture($this->owner);
        $this->gateway = new FakePaymentGateway();
        $this->app->instance(PaymentGatewayInterface::class, $this->gateway);
        config(['payments.zarinpal' => ['enabled' => true, 'merchant_id' => '00000000-0000-4000-8000-000000000000', 'sandbox' => true, 'callback_url' => 'https://clinic.example/payments/zarinpal/callback']]);
        $this->actingAs($this->owner);
    }
    private function hold(?string $start = null): Appointment
    {
        return app(BookingService::class)->create($this->bookingActor($this->owner), $this->bookingData($this->service, $start));
    }
    private function payload(Appointment $appointment, int $credit = 0): array
    {
        return ['idempotency_key' => (string) Str::uuid(), 'credit_irr' => $credit, 'expected_version' => $appointment->version];
    }
    private function begin(Appointment $appointment, int $credit = 0): PaymentAttempt
    {
        $response = $this->postJson('/api/v1/payments/'.$appointment->reference.'/initiate', $this->payload($appointment, $credit))->assertOk();
        return PaymentAttempt::where('reference', $response->json('data.reference'))->sole();
    }
    private function receiveCallback(PaymentAttempt $attempt, string $status = 'OK')
    {
        return $this->get('/payments/zarinpal/callback/'.$attempt->reference.'?'.http_build_query(['Authority' => $attempt->authority, 'Status' => $status]));
    }
    private function cancel(Appointment $appointment, ?string $key = null)
    {
        return $this->postJson('/api/v1/appointments/'.$appointment->reference.'/cancel', ['idempotency_key' => $key ?? (string) Str::uuid(), 'expected_version' => $appointment->version, 'reason' => 'لغو آزمایشی']);
    }
    private function creditedCancellation(): int
    {
        $appointment = $this->hold();
        $this->receiveCallback($this->begin($appointment))->assertOk();
        $this->cancel($appointment->fresh())->assertOk();
        return $appointment->price_irr;
    }
    public function test_full_amount_is_server_snapshot_and_network_is_outside_application_transaction(): void
    {
        $appointment = $this->hold();
        $this->get('/appointments/'.$appointment->reference)->assertOk()->assertHeader('Content-Security-Policy', "default-src 'self'; style-src 'self'; font-src 'self'; script-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self' https://payment.zarinpal.com https://sandbox.zarinpal.com");
        $baseline = DB::transactionLevel();
        $attempt = $this->begin($appointment);
        $this->assertSame(1000000, $attempt->amount_irr);
        $this->assertSame($baseline, $this->gateway->requests[0][4]);
        $this->assertSame('pending_payment', $appointment->fresh()->status);
        $this->assertStringNotContainsString('00000000-0000-4000', DB::table('payment_attempts')->value('merchant_secret'));
        $this->receiveCallback($attempt)->assertOk()->assertDontSee('بیمار آزمایشی')->assertDontSee('1,000,000');
        $this->assertSame($baseline, $this->gateway->verifications[0][2]);
        $this->assertSame('confirmed', $appointment->fresh()->status);
        $this->assertSame('paid', $appointment->fresh()->payment_status);
        $this->assertSame(0, $appointment->fresh()->balance_due_irr);
        $this->assertDatabaseCount('payments', 1);
    }
    public function test_duplicate_callback_and_initiation_do_not_create_duplicate_effects(): void
    {
        $appointment = $this->hold();
        $payload = $this->payload($appointment);
        $url = '/api/v1/payments/'.$appointment->reference.'/initiate';
        $first = $this->postJson($url, $payload)->assertOk()->json('data.reference');
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('data.reference', $first);
        $this->assertCount(1, $this->gateway->requests);
        $attempt = PaymentAttempt::where('reference', $first)->sole();
        $this->receiveCallback($attempt)->assertOk();
        $this->receiveCallback($attempt)->assertOk();
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1, DB::table('appointment_events')->where('action', 'confirmed')->count());
        $this->assertCount(1, $this->gateway->verifications);
    }
    public function test_web_payment_redirect_is_303_to_whitelisted_gateway_without_forwarding_form_body(): void
    {
        $appointment = $this->hold();
        $response = $this->post('/payments/'.$appointment->reference.'/initiate', $this->payload($appointment))->assertStatus(303);
        $this->assertStringStartsWith('https://sandbox.zarinpal.com/pg/StartPay/S', $response->headers->get('Location'));
        $this->assertStringContainsString("form-action 'self' https://payment.zarinpal.com https://sandbox.zarinpal.com", $response->headers->get('Content-Security-Policy'));
    }
    public function test_second_open_attempt_and_changed_idempotent_payload_are_rejected(): void
    {
        $appointment = $this->hold();
        $payload = $this->payload($appointment);
        $url = '/api/v1/payments/'.$appointment->reference.'/initiate';
        $this->postJson($url, $payload)->assertOk();
        $this->postJson($url, $this->payload($appointment))->assertConflict()->assertJsonPath('error.code', 'PAYMENT_IN_PROGRESS');
        $this->postJson($url, [...$payload, 'credit_irr' => 1])->assertConflict()->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
    }
    public function test_partial_credit_is_reserved_and_only_consumed_after_verified_remainder(): void
    {
        $this->creditedCancellation();
        $appointment = $this->hold();
        $attempt = $this->begin($appointment, 400000);
        $this->assertSame(600000, $attempt->amount_irr);
        $this->assertSame('pending_payment', $appointment->fresh()->status);
        $this->assertDatabaseHas('wallet_accounts', ['balance_irr' => 1000000, 'reserved_irr' => 400000]);
        $this->receiveCallback($attempt)->assertOk();
        $this->assertDatabaseHas('wallet_accounts', ['balance_irr' => 600000, 'reserved_irr' => 0]);
        $this->assertDatabaseHas('wallet_entries', ['amount_irr' => -400000, 'kind' => 'booking_payment']);
        $this->assertSame('confirmed', $appointment->fresh()->status);
    }
    public function test_full_credit_confirms_without_gateway_and_cancellation_restores_exactly_once(): void
    {
        $this->creditedCancellation();
        $appointment = $this->hold();
        $attempt = $this->begin($appointment, 1000000);
        $this->assertSame('settled', $attempt->state);
        $this->assertCount(1, $this->gateway->requests);
        $key = (string) Str::uuid();
        $appointment->refresh();
        $this->cancel($appointment, $key)->assertOk();
        $this->cancel($appointment, $key)->assertOk();
        $this->assertDatabaseHas('wallet_accounts', ['balance_irr' => 1000000, 'reserved_irr' => 0]);
        $this->assertSame(1, DB::table('wallet_entries')->where('business_key', 'cancel:'.$appointment->id)->count());
    }
    public function test_late_payment_credits_once_and_cannot_steal_reallocated_slot(): void
    {
        $old = $this->hold();
        $attempt = $this->begin($old);
        $this->travel(16)->minutes();
        $replacement = $this->hold();
        $this->receiveCallback($attempt)->assertOk();
        $this->receiveCallback($attempt)->assertOk();
        $this->assertSame('expired', $old->fresh()->effectiveStatus());
        $this->assertSame('pending_payment', $replacement->fresh()->status);
        $this->assertDatabaseHas('payments', ['allocated' => false, 'amount_irr' => 1000000]);
        $this->assertDatabaseCount('payment_reconciliations', 1);
        $this->assertDatabaseHas('wallet_accounts', ['balance_irr' => 1000000]);
    }
    public function test_expired_hold_is_never_revived_even_if_capacity_is_free(): void
    {
        $appointment = $this->hold();
        $attempt = $this->begin($appointment);
        $this->travelTo($appointment->hold_expires_at);
        $this->receiveCallback($attempt)->assertOk();
        $this->assertSame('expired', $appointment->fresh()->effectiveStatus());
        $this->assertSame('credited', $attempt->fresh()->state);
    }
    public function test_cancelled_inflight_payment_becomes_credit_without_restoring_appointment(): void
    {
        $this->creditedCancellation();
        $appointment = $this->hold();
        $attempt = $this->begin($appointment, 400000);
        $this->cancel($appointment)->assertOk();
        $this->assertDatabaseHas('wallet_accounts', ['balance_irr' => 1000000, 'reserved_irr' => 0]);
        $this->receiveCallback($attempt)->assertOk();
        $this->assertSame('cancelled', $appointment->fresh()->status);
        $this->assertDatabaseHas('wallet_accounts', ['balance_irr' => 1600000, 'reserved_irr' => 0]);
    }
    public function test_failed_payment_releases_credit_and_allows_new_attempt_within_original_hold(): void
    {
        $this->creditedCancellation();
        $appointment = $this->hold();
        $attempt = $this->begin($appointment, 400000);
        $this->gateway->paid = false;
        $this->receiveCallback($attempt)->assertOk();
        $this->assertSame('failed', $attempt->fresh()->state);
        $this->assertDatabaseHas('wallet_accounts', ['reserved_irr' => 0, 'balance_irr' => 1000000]);
        $second = $this->begin($appointment, 400000);
        $this->assertNotSame($second->id, $attempt->id);
        $this->assertTrue($appointment->hold_expires_at->equalTo($appointment->fresh()->hold_expires_at));
    }
    public function test_request_timeout_keeps_single_uncertain_attempt_and_reservation_until_expiry(): void
    {
        $this->creditedCancellation();
        $appointment = $this->hold();
        $payload = $this->payload($appointment, 400000);
        $this->gateway->requestTimeout = true;
        $url = '/api/v1/payments/'.$appointment->reference.'/initiate';
        $this->postJson($url, $payload)->assertStatus(503);
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('data.state', 'uncertain');
        $this->assertCount(2, $this->gateway->requests); // one earlier successful seed payment, one timed-out request
        $this->assertDatabaseHas('wallet_accounts', ['reserved_irr' => 400000]);
        $this->travel(16)->minutes();
        $this->artisan('booking:expire-holds')->assertSuccessful();
        $this->assertDatabaseHas('wallet_accounts', ['reserved_irr' => 0, 'balance_irr' => 1000000]);
    }
    public function test_verification_timeout_or_inquiry_alone_never_confirms_payment(): void
    {
        $appointment = $this->hold();
        $attempt = $this->begin($appointment);
        $this->gateway->verifyTimeout = true;
        $this->gateway->queryStatus = 'PAID';
        $this->receiveCallback($attempt)->assertOk();
        $this->assertSame('uncertain', $attempt->fresh()->state);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('pending_payment', $appointment->fresh()->status);
        $this->gateway->verifyTimeout = false;
        $this->receiveCallback($attempt)->assertOk();
        $this->assertSame('confirmed', $appointment->fresh()->status);
    }
    public function test_forged_authority_and_unknown_reference_cannot_trigger_verify(): void
    {
        $attempt = $this->begin($this->hold());
        $this->get('/payments/zarinpal/callback/'.$attempt->reference.'?Authority=S'.str_repeat('1', 35).'&Status=OK')->assertNotFound();
        $this->get('/payments/zarinpal/callback/'.Str::uuid().'?Authority='.$attempt->authority.'&Status=OK')->assertNotFound();
        $this->assertCount(0, $this->gateway->verifications);
        $this->assertDatabaseCount('payments', 0);
    }
    public function test_nok_callback_cannot_release_credit_without_definitive_server_inquiry(): void
    {
        $this->creditedCancellation();
        $appointment = $this->hold();
        $attempt = $this->begin($appointment, 400000);
        $this->gateway->queryStatus = 'IN_BANK';
        $this->receiveCallback($attempt, 'NOK')->assertOk();
        $this->assertSame('uncertain', $attempt->fresh()->state);
        $this->assertDatabaseHas('wallet_accounts', ['reserved_irr' => 400000]);
        $this->gateway->queryStatus = 'PAID';
        $this->receiveCallback($attempt, 'NOK')->assertOk();
        $this->assertSame('confirmed', $appointment->fresh()->status);
    }
    public function test_ownership_admin_settings_and_collection_permissions_are_enforced(): void
    {
        $appointment = $this->hold();
        $other = $this->booker('09123456788');
        $this->actingAs($other)->postJson('/api/v1/payments/'.$appointment->reference.'/initiate', $this->payload($appointment))->assertNotFound();
        $secretary = $this->booker('09123456787', 'secretary');
        $this->actingAs($secretary)->putJson('/admin/finance/emergency', ['enabled' => true, 'minutes' => 60, 'reason' => 'قطعی'])->assertForbidden();
        $this->actingAs($secretary)->get('/admin/finance')->assertForbidden();
        $this->actingAs($this->owner)->postJson('/payments/'.$appointment->reference.'/collect', ['amount_irr' => 1000000, 'receipt' => (string) Str::uuid(), 'reason' => 'نقدی', 'method' => 'cash'])->assertForbidden();
    }
    public function test_emergency_is_time_limited_admin_only_and_secretary_collects_exact_due_once(): void
    {
        $appointment = $this->hold();
        $admin = $this->booker('09123456786', 'admin');
        $this->actingAs($admin)->put('/admin/finance/emergency', ['enabled' => true, 'minutes' => '۶۰', 'reason' => 'قطعی آزمایشی'])->assertRedirect();
        $this->actingAs($this->owner)->postJson('/payments/'.$appointment->reference.'/emergency', ['expected_version' => 1, 'reason' => 'پرداخت در مطب'])->assertOk();
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('confirmed', $appointment->fresh()->status);
        $this->assertSame('unpaid', $appointment->fresh()->payment_status);
        $this->assertSame(1000000, $appointment->fresh()->balance_due_irr);
        $secretary = $this->booker('09123456787', 'secretary');
        $payload = ['amount_irr' => 1000000, 'receipt' => (string) Str::uuid(), 'reason' => 'رسید آزمایشی', 'method' => 'card'];
        $url = '/payments/'.$appointment->reference.'/collect';
        $this->actingAs($secretary)->postJson($url, [...$payload, 'amount_irr' => 1])->assertConflict();
        $this->postJson($url, $payload)->assertOk();
        $this->postJson($url, $payload)->assertOk();
        $this->postJson($url, [...$payload, 'receipt' => (string) Str::uuid()])->assertConflict();
        $this->assertDatabaseHas('payments', ['provider' => 'clinic', 'actor_id' => $secretary->id]);
        $this->assertSame(1, DB::table('financial_events')->where('action', 'payment.clinic')->count());
        $this->actingAs($this->owner);
        $this->cancel($appointment->fresh())->assertOk();
        $this->assertDatabaseHas('wallet_accounts', ['balance_irr' => 1000000]);
    }
    public function test_expired_emergency_or_open_gateway_attempt_cannot_confirm_unpaid(): void
    {
        $appointment = $this->hold();
        $url = '/payments/'.$appointment->reference.'/emergency';
        $data = ['expected_version' => 1, 'reason' => 'پرداخت در مطب'];
        $this->postJson($url, $data)->assertConflict()->assertJsonPath('error.code', 'EMERGENCY_DISABLED');
        DB::table('payment_settings')->where('id', 1)->update(['emergency_until' => now()->addMinute()]);
        $this->begin($appointment);
        $this->postJson($url, $data)->assertConflict()->assertJsonPath('error.code', 'PAYMENT_IN_PROGRESS');
        $this->travel(2)->minutes();
        $this->postJson($url, $data)->assertConflict()->assertJsonPath('error.code', 'EMERGENCY_DISABLED');
    }
    public function test_payment_in_progress_freezes_reschedule_and_preserves_original_slot(): void
    {
        $appointment = $this->hold();
        $this->begin($appointment);
        $this->postJson('/api/v1/appointments/'.$appointment->reference.'/reschedule', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 1, 'start_at' => $appointment->start_at->addDay()->toIso8601String()])->assertConflict()->assertJsonPath('error.code', 'PAYMENT_IN_PROGRESS');
        $this->assertSame(1, $appointment->fresh()->version);
    }
    public function test_guest_credit_ownership_survives_optional_account_login_without_cross_phone_access(): void
    {
        $actor = new BookingActor(null, '09123456788', [], (string) config('app.key'));
        $appointment = app(BookingService::class)->create($actor, $this->bookingData($this->service));
        $attempt = app(PaymentService::class)->initiate($actor, $appointment, $this->payload($appointment));
        $this->receiveCallback($attempt)->assertOk();
        app(BookingService::class)->change($actor, $appointment->fresh(), ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'reason' => 'لغو'], 'cancel');
        $this->get('/wallet')->assertOk()->assertSee('موجودی: 0 ریال');
        $guestUser = $this->booker('09123456788');
        $this->actingAs($guestUser)->get('/wallet')->assertOk()->assertSee('1,000,000');
    }
    public function test_refund_request_is_idempotent_and_rejection_restores_credit_once(): void
    {
        $this->creditedCancellation();
        $payload = ['idempotency_key' => (string) Str::uuid(), 'amount_irr' => 600000, 'reason' => 'استرداد آزمایشی'];
        $reference = $this->postJson('/wallet/refunds', $payload)->assertOk()->json('data.reference');
        $this->postJson('/wallet/refunds', $payload)->assertOk()->assertJsonPath('data.reference', $reference);
        $this->postJson('/wallet/refunds', [...$payload, 'amount_irr' => 1])->assertConflict();
        $this->assertDatabaseHas('wallet_accounts', ['balance_irr' => 400000]);
        $admin = $this->booker('09123456786', 'admin');
        $decision = ['decision' => 'rejected', 'reason' => 'رد آزمایشی'];
        $this->actingAs($admin)->post('/admin/finance/refunds/'.$reference, $decision)->assertRedirect();
        $this->post('/admin/finance/refunds/'.$reference, $decision)->assertRedirect();
        $this->assertDatabaseHas('wallet_accounts', ['balance_irr' => 1000000]);
        $this->assertSame(1, DB::table('wallet_entries')->where('kind', 'refund_reversed')->count());
    }
    public function test_manual_refund_completion_requires_processing_evidence_and_admin_without_second_debit(): void
    {
        $this->creditedCancellation();
        $reference = $this->postJson('/wallet/refunds', ['idempotency_key' => (string) Str::uuid(), 'amount_irr' => 1000000, 'reason' => 'استرداد'])->assertOk()->json('data.reference');
        $url = '/admin/finance/refunds/'.$reference;
        $decision = ['decision' => 'completed', 'reason' => 'سند مصنوعی انتقال', 'bank_reference' => 'TEST-BANK-123'];
        $this->postJson($url, $decision)->assertForbidden();
        $admin = $this->booker('09123456786', 'admin');
        $this->actingAs($admin)->postJson($url, $decision)->assertConflict();
        $this->post($url, ['decision' => 'processing', 'reason' => 'بررسی'])->assertRedirect();
        $this->postJson($url, ['decision' => 'completed', 'reason' => 'سند'])->assertUnprocessable();
        $this->post($url, $decision)->assertRedirect();
        $this->post($url, $decision)->assertRedirect();
        $this->postJson($url, ['decision' => 'rejected', 'reason' => 'برگشت'])->assertConflict();
        $this->assertDatabaseHas('wallet_accounts', ['balance_irr' => 0]);
        $this->assertSame(1, DB::table('wallet_entries')->where('kind', 'refund_reserved')->count());
        $this->assertSame(1, DB::table('financial_events')->where('action', 'refund.completed')->count());
    }
    public function test_refund_and_credit_spend_cannot_exceed_available_or_reserved_balance(): void
    {
        $this->creditedCancellation();
        $attempt = $this->begin($this->hold(), 800000);
        $this->postJson('/wallet/refunds', ['idempotency_key' => (string) Str::uuid(), 'amount_irr' => 300000, 'reason' => 'استرداد'])->assertConflict()->assertJsonPath('error.code', 'INSUFFICIENT_CREDIT');
        $this->assertDatabaseCount('refunds', 0);
        $this->assertTrue($attempt->credit_reserved);
    }
    public function test_repeated_provider_reference_never_allocates_second_booking_or_mints_credit(): void
    {
        $first = $this->hold();
        $this->gateway->forcedReference = '123456';
        $this->receiveCallback($this->begin($first))->assertOk();
        $second = $this->hold($first->start_at->addDay()->toIso8601String());
        $attempt = $this->begin($second);
        $this->receiveCallback($attempt)->assertOk();
        $this->assertSame('pending_payment', $second->fresh()->status);
        $this->assertSame('uncertain', $attempt->fresh()->state);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('wallet_entries', 0);
        $this->assertDatabaseHas('payment_reconciliations', ['reason' => 'reference_collision', 'resolution' => 'requires_review']);
    }
    public function test_free_service_is_fully_settled_without_external_payment_and_can_cancel(): void
    {
        $this->service->update(['price_irr' => 0]);
        $appointment = $this->hold();
        $this->assertSame('settled', $this->begin($appointment)->state);
        $this->assertCount(0, $this->gateway->requests);
        $this->cancel($appointment->fresh())->assertOk();
        $this->assertDatabaseCount('wallet_entries', 0);
    }
    public function test_gateway_disabled_and_stale_quote_do_not_reserve_credit_or_call_network(): void
    {
        $appointment = $this->hold();
        config(['payments.zarinpal.enabled' => false]);
        $this->postJson('/api/v1/payments/'.$appointment->reference.'/initiate', $this->payload($appointment))->assertStatus(503)->assertJsonPath('error.code', 'GATEWAY_DISABLED');
        $this->postJson('/api/v1/payments/'.$appointment->reference.'/initiate', [...$this->payload($appointment), 'expected_version' => 10])->assertConflict()->assertJsonPath('error.code', 'STALE_APPOINTMENT');
        $this->assertDatabaseCount('payment_attempts', 0);
        $this->assertCount(0, $this->gateway->requests);
    }
    public function test_csrf_is_required_for_initiation_but_callback_has_narrow_exception(): void
    {
        $appointment = $this->hold();
        $attempt = $this->begin($appointment);
        $this->app->instance('env', 'local');
        $this->post('/api/v1/payments/'.$appointment->reference.'/initiate', $this->payload($appointment))->assertStatus(419);
        $this->post('/payments/zarinpal/callback/'.$attempt->reference, ['Authority' => $attempt->authority, 'Status' => 'OK'])->assertOk();
        $this->assertSame('confirmed', $appointment->fresh()->status);
    }
    public function test_price_duration_changes_do_not_change_held_quote_but_closure_prevents_confirmation(): void
    {
        $appointment = $this->hold();
        $attempt = $this->begin($appointment);
        $this->service->update(['price_irr' => 2000000, 'duration_minutes' => 60]);
        $this->receiveCallback($attempt)->assertOk();
        $this->assertSame('confirmed', $appointment->fresh()->status);
        $this->assertSame(1000000, $appointment->fresh()->price_irr);
        $this->assertSame(30, $appointment->fresh()->duration_minutes);
        $this->cancel($appointment->fresh())->assertOk();
        $this->service->refresh();
        $next = $this->hold();
        $attempt = $this->begin($next);
        app(\App\Modules\Scheduling\Application\ScheduleService::class)->saveException(['start_date' => $next->local_date, 'end_date' => $next->local_date, 'kind' => 'closed', 'shifts' => [], 'reason' => 'تعطیلی آزمایشی'], $this->owner->id);
        $this->receiveCallback($attempt)->assertOk();
        $this->assertSame('pending_payment', $next->fresh()->status);
        $this->assertSame('credited', $attempt->fresh()->state);
        $this->assertDatabaseHas('wallet_accounts', ['balance_irr' => 3000000]);
    }
    public function test_mixed_payment_cancel_returns_full_bank_plus_consumed_credit_and_paid_move_is_allowed(): void
    {
        $this->creditedCancellation();
        $appointment = $this->hold();
        $this->receiveCallback($this->begin($appointment, 400000))->assertOk();
        $appointment->refresh();
        $this->postJson('/api/v1/appointments/'.$appointment->reference.'/reschedule', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => $appointment->version, 'start_at' => $appointment->start_at->addDay()->toIso8601String()])->assertOk();
        $this->cancel($appointment->fresh())->assertOk();
        $this->assertDatabaseHas('wallet_accounts', ['balance_irr' => 1600000, 'reserved_irr' => 0]);
        $this->assertSame(1000000, (int) DB::table('wallet_entries')->where('business_key', 'cancel:'.$appointment->id)->value('amount_irr'));
    }
    public function test_staff_cannot_spend_another_bookers_credit_and_client_amount_or_status_are_ignored(): void
    {
        $appointment = $this->hold();
        $secretary = $this->booker('09123456787', 'secretary');
        $this->actingAs($secretary)->postJson('/api/v1/payments/'.$appointment->reference.'/initiate', $this->payload($appointment, 1))->assertForbidden();
        $this->actingAs($this->owner)->postJson('/api/v1/payments/'.$appointment->reference.'/initiate', [...$this->payload($appointment), 'amount_irr' => 1, 'payment_status' => 'paid', 'provider_reference' => 'fake'])->assertOk();
        $this->assertDatabaseHas('payment_attempts', ['amount_irr' => 1000000]);
        $this->assertSame('unpaid', $appointment->fresh()->payment_status);
        $this->assertDatabaseCount('payments', 0);
    }
    public function test_failed_cancellation_rolls_back_credit_and_status_without_sql_or_secret_in_response(): void
    {
        $appointment = $this->hold();
        $this->receiveCallback($this->begin($appointment))->assertOk();
        \Illuminate\Support\Facades\Log::spy();
        Appointment::updating(function ($model): void {
            if ($model->status === 'cancelled') {
                throw new \Illuminate\Database\QueryException('mysql', 'UPDATE appointments SET status = ?', ['PRIVATE_FINANCIAL_BINDING'], new \PDOException('synthetic failure'));
            }
        });
        $this->cancel($appointment->fresh())->assertStatus(503)->assertDontSee('PRIVATE_FINANCIAL_BINDING');
        $this->assertDatabaseCount('wallet_entries', 0);
        $this->assertSame('confirmed', $appointment->fresh()->status);
        $this->assertSame('paid', $appointment->fresh()->payment_status);
        \Illuminate\Support\Facades\Log::shouldHaveReceived('error')->once()->with('booking.database_error', \Mockery::type('array'));
    }
}
