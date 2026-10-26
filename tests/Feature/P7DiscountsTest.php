<?php

namespace Tests\Feature;

use App\Modules\Billing\Application\DiscountService;
use App\Modules\Booking\Application\BookingService;
use App\Modules\Booking\Domain\BookingFailure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\P7Fixtures;
use Tests\TestCase;

final class P7DiscountsTest extends TestCase
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
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-26 08:00', 'Asia/Tehran'));
        $this->seed();
        $this->admin = $this->booker('09120000001', 'admin');
        $this->secretary = $this->booker('09120000002', 'secretary');
        $this->patient = $this->booker();
        $this->service = $this->bookingServiceFixture($this->admin);
    }
    public function test_admin_creates_scoped_offer_with_jalali_days_audit_and_immutable_policy(): void
    {
        $data = ['code' => 'save20', 'kind' => 'percent', 'value' => '۲۰', 'max_uses' => 5, 'per_patient' => 1, 'start_jalali' => '۱۴۰۵-۰۸-۰۴', 'end_jalali' => '۱۴۰۵-۰۸-۰۴', 'service_ids' => [$this->service->id], 'reason' => 'آزمون'];
        $this->actingAs($this->secretary)->post('/admin/discounts', $data)->assertForbidden();
        $this->actingAs($this->admin)->post('/admin/discounts', $data)->assertRedirect();
        $offer = DB::table('discounts')->sole();
        $this->assertSame('SAVE20', $offer->code);
        $this->assertSame('2026-10-25 20:30:00', $offer->starts_at);
        $this->assertSame('2026-10-26 20:30:00', $offer->ends_at);
        $this->assertDatabaseHas('configuration_audit_events', ['action' => 'discount.created']);
        $this->post('/admin/discounts/'.$offer->id, ['expected_version' => 1, 'is_active' => 0, 'value' => 99, 'reason' => 'توقف'])->assertRedirect();
        $this->assertDatabaseHas('discounts', ['value' => 20, 'is_active' => false, 'version' => 2]);
        $this->post('/admin/discounts/'.$offer->id, ['expected_version' => 1, 'is_active' => 1, 'reason' => 'نسخه قدیم'])->assertConflict();
    }
    public function test_malformed_offer_dates_percent_and_duplicate_code_are_rejected(): void
    {
        $data = ['code' => 'SAVE20', 'kind' => 'percent', 'value' => 101, 'max_uses' => 1, 'per_patient' => 1, 'start_jalali' => '1405-08-04', 'end_jalali' => '1405-08-04', 'service_ids' => [$this->service->id], 'reason' => 'تست'];
        $this->actingAs($this->admin)->postJson('/admin/discounts', $data)->assertUnprocessable();
        $this->postJson('/admin/discounts', [...$data, 'value' => 20, 'start_jalali' => '1405-13-01'])->assertUnprocessable();
        $this->offer();
        $this->postJson('/admin/discounts', [...$data, 'value' => 20])->assertUnprocessable();
        $this->assertDatabaseCount('discounts', 1);
    }
    public function test_percent_rounding_cap_and_fixed_discount_never_exceed_server_base(): void
    {
        $this->service->update(['price_irr' => 1000001]);
        $this->offer(['value' => 33, 'max_discount_irr' => 100000]);
        $appointment = $this->discounted();
        $this->assertSame(1000001, $appointment->base_price_irr);
        $this->assertSame(100000, $appointment->discount_irr);
        $this->assertSame(900001, $appointment->price_irr);
        $this->assertSame(900001, $appointment->balance_due_irr);
        $this->offer(['code' => 'ALLFREE', 'kind' => 'fixed', 'value' => 2000000]);
        $second = $this->discounted(['discount_code' => 'ALLFREE', 'start_at' => $appointment->start_at->addHour()->toIso8601String()]);
        $this->assertSame(0, $second->price_irr);
        $this->assertSame(1000001, $second->discount_irr);
    }
    public function test_one_third_percent_is_integer_floor_without_float_rounding(): void
    {
        $this->service->update(['price_irr' => 1000001]);
        $this->offer(['value' => 33]);
        $this->assertSame(330000, $this->discounted()->discount_irr);
    }
    public function test_discount_preview_does_not_hold_capacity_patient_or_quota(): void
    {
        $this->offer();
        $this->actingAs($this->patient)->postJson('/api/v1/discount-preview', ['service_id' => $this->service->id, 'discount_code' => 'save20'])->assertOk()->assertJsonPath('data.net_irr', 800000)->assertJsonPath('meta.estimate_only', true);
        $this->assertDatabaseCount('patients', 0);
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('discount_redemptions', 0);
    }
    public function test_same_booking_key_reserves_and_consumes_coupon_only_once(): void
    {
        $this->offer();
        $data = [...$this->bookingData($this->service), 'discount_code' => 'SAVE20'];
        $actor = $this->bookingActor($this->patient);
        $first = app(BookingService::class)->create($actor, $data);
        $second = app(BookingService::class)->create($actor, $data);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('discount_redemptions', 1);
        $paid = $this->collect($first);
        $this->assertSame(800000, $paid->price_irr);
        $this->assertDatabaseHas('payments', ['amount_irr' => 800000, 'allocated' => true]);
        $this->assertDatabaseHas('discount_redemptions', ['state' => 'consumed']);
        $this->assertDatabaseCount('discount_events', 2);
    }
    public function test_per_patient_limit_uses_patient_id_and_expired_hold_frees_total_quota_without_cron(): void
    {
        $this->offer(['max_uses' => 1, 'per_patient' => 1]);
        $first = $this->discounted();
        try {
            $this->discounted(['patient_id' => $first->patient_id, 'start_at' => $first->start_at->addHour()->toIso8601String()]);
            $this->fail('Quota exceeded');
        } catch (BookingFailure $error) {
            $this->assertSame('DISCOUNT_UNAVAILABLE', $error->errorCode);
        }
        $this->travel(16)->minutes();
        $second = $this->discounted(['patient_id' => $first->patient_id]);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(800000, $second->price_irr);
        $this->assertDatabaseCount('patients', 1);
    }
    public function test_paid_cancel_returns_only_net_credit_and_consumed_coupon_is_not_recycled(): void
    {
        $this->offer(['max_uses' => 1]);
        $paid = $this->collect($this->discounted());
        $this->cancel($paid);
        $this->assertDatabaseHas('wallet_entries', ['kind' => 'cancellation', 'amount_irr' => 800000]);
        $this->assertDatabaseHas('discount_redemptions', ['state' => 'consumed']);
        $this->expectException(BookingFailure::class);
        $this->discounted();
    }
    public function test_unpaid_cancel_releases_coupon_and_a_later_expiry_job_is_idempotent(): void
    {
        $this->offer(['max_uses' => 1]);
        $first = $this->discounted();
        $this->cancel($first);
        app(BookingService::class)->expire($first->id);
        $this->assertDatabaseHas('discount_redemptions', ['appointment_id' => $first->id, 'state' => 'released']);
        $this->assertDatabaseCount('discount_events', 2);
        $this->assertSame(800000, $this->discounted()->price_irr);
    }
    public function test_offer_disabling_or_tariff_change_does_not_reprice_an_existing_hold(): void
    {
        $id = $this->offer();
        $held = $this->discounted();
        app(DiscountService::class)->toggle($this->admin, $id, 1, false, 'توقف');
        $this->service->update(['price_irr' => 2000000]);
        $this->assertSame(800000, $this->collect($held)->price_irr);
        $this->assertDatabaseHas('discount_redemptions', ['offer_version' => 1, 'state' => 'consumed']);
    }
    public function test_coupon_100_percent_settles_without_gateway_and_does_not_mint_credit(): void
    {
        $this->offer(['value' => 100]);
        $held = $this->discounted();
        $this->actingAs($this->patient)->postJson('/api/v1/payments/'.$held->reference.'/initiate', ['credit_irr' => 0, 'expected_version' => 1, 'idempotency_key' => (string) Str::uuid()])->assertSuccessful();
        $this->assertDatabaseHas('appointments', ['id' => $held->id, 'status' => 'confirmed', 'payment_status' => 'paid', 'price_irr' => 0]);
        $this->assertDatabaseHas('discount_redemptions', ['state' => 'consumed']);
        $this->assertDatabaseCount('wallet_entries', 0);
    }
    public function test_offer_not_yet_started_expired_disabled_wrong_service_or_unknown_is_rejected_atomically(): void
    {
        $id = $this->offer(['starts_at' => now()->addHour()]);
        foreach ([['starts_at' => now()->addHour()], ['starts_at' => now()->subDay(), 'ends_at' => now()], ['ends_at' => now()->addDay(), 'is_active' => false], ['is_active' => true, 'service_ids' => '[]']] as $changes) {
            DB::table('discounts')->where('id', $id)->update($changes);
            try {
                $this->discounted();
                $this->fail('Invalid offer accepted');
            } catch (BookingFailure $error) {
                $this->assertSame('DISCOUNT_UNAVAILABLE', $error->errorCode);
            }
        }
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('patients', 0);
    }
}
