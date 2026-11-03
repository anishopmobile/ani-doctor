<?php

namespace Tests\Feature;

use App\Modules\Booking\Application\BookingService;
use App\Modules\Payments\Application\CardTransferService;
use App\Modules\Payments\Infrastructure\CardTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

final class CardTransferTest extends TestCase
{
    use RefreshDatabase;
    use BookingFixtures;

    private $owner;
    private $secretary;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
        $this->owner = $this->booker();
        $this->secretary = $this->booker('09120000002', 'secretary');
        $admin = $this->booker('09120000003', 'admin');
        $this->service = $this->bookingServiceFixture($admin);
        app(CardTransferService::class)->configure($this->bookingActor($admin), ['enabled' => true, 'card_number' => '0000000000000000', 'holder' => 'آزمایشی', 'expected_version' => 0]);
        $this->actingAs($this->owner);
    }

    private function hold()
    {
        $appointment = app(BookingService::class)->create($this->bookingActor($this->owner), $this->bookingData($this->service));
        $this->postJson('/payments/'.$appointment->reference.'/card/begin', ['expected_version' => $appointment->version])->assertOk();
        return $appointment->fresh();
    }

    private function submit($appointment)
    {
        return $this->postJson('/payments/'.$appointment->reference.'/card/submit', ['receipt' => UploadedFile::fake()->image('receipt.png')]);
    }

    public function test_receipt_confirms_without_minting_money_and_survives_expiry_then_review_is_idempotent(): void
    {
        $appointment = $this->hold();
        $this->get('/appointments/'.$appointment->reference)->assertOk()->assertSee('ارسال رسید و قطعی‌کردن نوبت');
        $this->submit($appointment)->assertOk();
        $this->assertSame('confirmed', $appointment->fresh()->status);
        $this->assertSame('card_pending', $appointment->fresh()->payment_status);
        $this->assertDatabaseCount('payments', 0);
        $this->travel(16)->minutes();
        app(BookingService::class)->expire($appointment->id);
        $this->assertSame('confirmed', $appointment->fresh()->status);
        $this->actingAs($this->secretary);
        $this->get('/management/card-transfers')->assertOk()->assertSee('در انتظار بررسی رسید');
        foreach (range(1, 2) as $n) {
            $this->postJson('/payments/'.$appointment->reference.'/card/review', ['decision' => 'approve', 'reason' => 'تطبیق شد'])->assertOk();
        }
        $this->assertSame('paid', $appointment->fresh()->payment_status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame($this->secretary->id, CardTransfer::sole()->reviewed_by);
        $this->assertDatabaseCount('wallet_entries', 0);
    }

    public function test_exact_deadline_rejects_receipt_and_cannot_revive_expired_capacity(): void
    {
        $appointment = $this->hold();
        $this->travelTo(CardTransfer::sole()->deadline);
        $this->submit($appointment)->assertConflict()->assertJsonPath('error.code', 'HOLD_EXPIRED');
        $this->assertSame('expired', $appointment->fresh()->effectiveStatus());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_one_second_before_deadline_succeeds_and_foreign_actor_cannot_read_or_review(): void
    {
        $appointment = $this->hold();
        $this->travelTo(CardTransfer::sole()->deadline->subSecond());
        $this->submit($appointment)->assertOk();
        $this->postJson('/payments/'.$appointment->reference.'/card/review', ['decision' => 'approve', 'reason' => 'جعلی'])->assertForbidden();
        $this->actingAs($this->booker('09120000004'));
        $this->get('/payments/'.$appointment->reference.'/card/receipt')->assertNotFound();
        $this->submit($appointment)->assertNotFound();
    }

    public function test_rejection_of_future_appointment_releases_capacity_and_enqueues_private_notification(): void
    {
        $appointment = $this->hold();
        DB::table('bale_links')->insert(['bale_id' => '123', 'user_id' => $this->owner->id, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->submit($appointment)->assertOk();
        $this->actingAs($this->secretary);
        $this->postJson('/payments/'.$appointment->reference.'/card/review', ['decision' => 'reject', 'reason' => 'مغایرت مبلغ'])->assertOk();
        $this->assertSame('cancelled', $appointment->fresh()->status);
        $this->assertSame('card_rejected', $appointment->fresh()->payment_status);
        $this->assertDatabaseCount('bale_outbox', 1);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('wallet_entries', 0);
        $replacement = app(BookingService::class)->create($this->bookingActor($this->owner), $this->bookingData($this->service, $appointment->start_at->toIso8601String()));
        $this->assertSame('pending_payment', $replacement->status);
    }

    public function test_reject_after_attendance_or_start_requires_manager_and_preserves_appointment(): void
    {
        $appointment = $this->hold();
        $this->submit($appointment)->assertOk();
        $appointment->forceFill(['attendance_status' => 'arrived', 'arrived_at' => now()])->save();
        $this->actingAs($this->secretary);
        $this->postJson('/payments/'.$appointment->reference.'/card/review', ['decision' => 'reject', 'reason' => 'نیاز به بررسی'])->assertOk()->assertJsonPath('data.state', 'manager_review');
        $this->assertSame('confirmed', $appointment->fresh()->status);
        $this->assertSame('arrived', $appointment->fresh()->attendance_status);
        $this->assertSame('reconciliation_required', $appointment->fresh()->payment_status);
    }

    public function test_zarinpal_cannot_start_on_card_hold_and_secretary_cannot_change_card(): void
    {
        $appointment = $this->hold();
        $this->postJson('/api/v1/payments/'.$appointment->reference.'/initiate', ['idempotency_key' => (string) \Illuminate\Support\Str::uuid(), 'expected_version' => $appointment->version, 'credit_irr' => 0])->assertConflict()->assertJsonPath('error.code', 'PAYMENT_IN_PROGRESS');
        $this->actingAs($this->secretary)->putJson('/admin/card-settings', ['enabled' => true, 'card_number' => '0000000000000000', 'holder' => 'آزمایشی', 'expected_version' => 1])->assertForbidden();
    }

    public function test_reject_after_start_without_attendance_also_preserves_history(): void
    {
        $appointment = $this->hold();
        $this->submit($appointment)->assertOk();
        $this->travelTo($appointment->start_at->addSecond());
        $this->actingAs($this->secretary);
        $this->postJson('/payments/'.$appointment->reference.'/card/review', ['decision' => 'reject', 'reason' => 'مغایرت پس از موعد'])->assertOk()->assertJsonPath('data.state', 'manager_review');
        $this->assertSame('confirmed', $appointment->fresh()->status);
        $this->assertSame('scheduled', $appointment->fresh()->attendance_status);
        $this->assertDatabaseCount('wallet_entries', 0);
    }

    public function test_replayed_begin_and_receipt_do_not_extend_deadline_or_replace_evidence(): void
    {
        $appointment = $this->hold();
        $deadline = CardTransfer::sole()->deadline;
        $this->travel(3)->minutes();
        $this->postJson('/payments/'.$appointment->reference.'/card/begin', ['expected_version' => $appointment->version])->assertOk();
        $this->assertTrue($deadline->equalTo(CardTransfer::sole()->deadline));
        $file = UploadedFile::fake()->image('receipt.png');
        $this->postJson('/payments/'.$appointment->reference.'/card/submit', ['receipt' => $file])->assertOk();
        $this->postJson('/payments/'.$appointment->reference.'/card/submit', ['receipt' => $file])->assertOk();
        $this->assertDatabaseCount('card_transfers', 1);
        $this->assertSame(1, DB::table('appointment_events')->where('action', 'confirmed')->count());
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_card_cannot_start_after_fifteen_minutes_even_if_site_hold_is_longer(): void
    {
        DB::table('booking_settings')->where('id', 1)->update(['hold_minutes' => 30]);
        $appointment = app(BookingService::class)->create($this->bookingActor($this->owner), $this->bookingData($this->service));
        $this->travel(16)->minutes();
        $this->postJson('/payments/'.$appointment->reference.'/card/begin', ['expected_version' => $appointment->version])->assertConflict()->assertJsonPath('error.code', 'HOLD_EXPIRED');
        $this->assertDatabaseCount('card_transfers', 0);
    }
}
