<?php

namespace Tests\Feature;

use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Catalog\Infrastructure\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

final class P3BookingTest extends TestCase
{
    use RefreshDatabase;
    use BookingFixtures;
    private $owner;
    private Service $service;
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->travelTo(CarbonImmutable::parse('2026-10-25 08:00', 'Asia/Tehran'));
        $this->owner = $this->booker();
        $this->service = $this->bookingServiceFixture($this->owner);
    }
    private function create(array $changes = []): Appointment
    {
        $data = [...$this->bookingData($this->service),...$changes];
        $response = $this->actingAs($this->owner)->postJson('/api/v1/appointments', $data)->assertCreated()->assertJsonPath('data.booking_status', 'pending_payment');
        return Appointment::where('reference', $response->json('data.reference'))->sole();
    }
    private function mutation(Appointment $appointment, array $changes = []): array
    {
        return ['idempotency_key' => (string)Str::uuid(),'expected_version' => $appointment->version,'reason' => 'درخواست بیمار',...$changes];
    }
    public function test_unverified_or_expired_guest_proof_cannot_create(): void
    {
        $data = $this->bookingData($this->service);
        $this->postJson('/api/v1/appointments', $data)->assertUnauthorized();
        $this->withSession(['guest_identity' => ['mobile' => '09120000000','expires_at' => now()->timestamp]])->postJson('/api/v1/appointments', $data)->assertUnauthorized();
        $this->assertDatabaseCount('appointments', 0);
    }
    public function test_guest_reserves_without_account_and_can_only_read_own_phone(): void
    {
        $before = DB::table('users')->count();
        $this->withSession(['guest_identity' => ['mobile' => '09120000000','expires_at' => now()->addMinutes(10)->timestamp]])
            ->postJson('/api/v1/appointments', $this->bookingData($this->service))->assertCreated();
        $appointment = Appointment::sole();
        $this->assertNull($appointment->booker_user_id);
        $this->assertSame($before, DB::table('users')->count());
        $this->get('/appointments/'.$appointment->reference)->assertOk()->assertSee('رزرو موقت');
        $this->withSession(['guest_identity' => ['mobile' => '09120000001','expires_at' => now()->addMinutes(10)->timestamp]])
            ->get('/appointments/'.$appointment->reference)->assertNotFound();
    }
    public function test_user_hold_snapshots_price_and_ignores_privileged_payload(): void
    {
        $appointment = $this->create(['status' => 'confirmed','payment_status' => 'paid','booker_user_id' => 999,'hold_expires_at' => '2099-01-01']);
        $this->assertSame('unpaid', $appointment->payment_status);
        $this->assertSame($this->owner->id, $appointment->booker_user_id);
        $this->assertSame(900, $appointment->hold_expires_at->timestamp - now()->timestamp);
        $this->assertDatabaseHas('patient_user_links', ['patient_id' => $appointment->patient_id,'user_id' => $this->owner->id]);
        $this->service->update(['price_irr' => 2000000]);
        $this->assertSame(1000000, $appointment->fresh()->price_irr);
    }
    public function test_repeated_create_has_one_hold_patient_and_event(): void
    {
        $data = $this->bookingData($this->service);
        $this->actingAs($this->owner)->postJson('/api/v1/appointments', $data)->assertCreated();
        $this->postJson('/api/v1/appointments', $data)->assertCreated();
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('patients', 1);
        $this->assertDatabaseCount('appointment_events', 1);
        $this->postJson('/api/v1/appointments', [...$data,'patient_name' => 'نام دیگر'])->assertConflict()->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
    }
    public function test_last_capacity_is_checked_in_server_and_reader_blocks_it(): void
    {
        $first = $this->create();
        $other = $this->booker('09123456788');
        $this->actingAs($other)->postJson('/api/v1/appointments', $this->bookingData($this->service))->assertConflict()->assertJsonPath('error.code', 'SLOT_UNAVAILABLE');
        $this->assertDatabaseCount('patients', 1);
        $this->getJson('/api/v1/availability?service_id='.$this->service->id.'&date='.$first->local_date)->assertJsonPath('data.slots.18.is_bookable', false);
    }
    public function test_expiry_is_effective_without_job_and_job_is_idempotent(): void
    {
        $appointment = $this->create();
        $this->travel(15)->minutes();
        $this->assertSame('expired', $appointment->fresh()->effectiveStatus());
        $other = $this->booker('09123456788');
        $this->actingAs($other)->postJson('/api/v1/appointments', $this->bookingData($this->service))->assertCreated();
        $this->artisan('booking:expire-holds')->assertSuccessful();
        $this->artisan('booking:expire-holds')->assertSuccessful();
        $this->assertSame('expired', $appointment->fresh()->status);
        $this->assertSame(1, DB::table('appointment_events')->where('appointment_id', $appointment->id)->where('action', 'expired')->count());
    }
    public function test_unavailable_reschedule_preserves_old_slot_and_deadline(): void
    {
        $first = $this->create();
        $other = $this->booker('09123456788');
        $target = $first->start_at->addHour()->toIso8601String();
        $this->actingAs($other)->postJson('/api/v1/appointments', $this->bookingData($this->service, $target))->assertCreated();
        $this->actingAs($this->owner)->postJson('/api/v1/appointments/'.$first->reference.'/reschedule', $this->mutation($first, ['start_at' => $target]))->assertConflict();
        $this->assertSame($first->start_at->timestamp, $first->fresh()->start_at->timestamp);
        $this->assertSame(1, $first->fresh()->version);
    }
    public function test_overlapping_self_reschedule_is_atomic_and_does_not_extend_hold(): void
    {
        $this->service->update(['duration_minutes' => 60,'buffer_minutes' => 15]);
        $appointment = $this->create();
        $this->travel(60)->seconds();
        $data = $this->mutation($appointment, ['start_at' => $appointment->start_at->addMinutes(30)->toIso8601String()]);
        $this->postJson('/api/v1/appointments/'.$appointment->reference.'/reschedule', $data)->assertOk();
        $this->postJson('/api/v1/appointments/'.$appointment->reference.'/reschedule', $data)->assertOk();
        $this->assertSame($appointment->start_at->addMinutes(30)->timestamp, $appointment->fresh()->start_at->timestamp);
        $this->assertSame($appointment->hold_expires_at->timestamp, $appointment->fresh()->hold_expires_at->timestamp);
        $this->assertSame(2, $appointment->fresh()->version);
        $this->assertDatabaseCount('appointment_events', 2);
    }
    public function test_other_user_cannot_view_edit_cancel_or_reuse_patient_id(): void
    {
        $appointment = $this->create();
        $other = $this->booker('09123456788');
        $this->actingAs($other)->get('/appointments/'.$appointment->reference)->assertNotFound();
        $this->postJson('/api/v1/appointments/'.$appointment->reference.'/cancel', $this->mutation($appointment))->assertNotFound();
        $this->postJson('/api/v1/appointments/'.$appointment->reference.'/reschedule', $this->mutation($appointment, ['start_at' => $appointment->start_at->addDay()->toIso8601String()]))->assertNotFound();
        $this->postJson('/api/v1/appointments', [...$this->bookingData($this->service, $appointment->start_at->addDay()->toIso8601String()),'patient_id' => $appointment->patient_id])->assertNotFound();
    }
    public function test_cancel_is_audited_and_replay_has_no_second_effect(): void
    {
        $appointment = $this->create();
        $data = $this->mutation($appointment);
        $this->postJson('/api/v1/appointments/'.$appointment->reference.'/cancel', $data)->assertOk()->assertJsonPath('data.booking_status', 'cancelled');
        $this->postJson('/api/v1/appointments/'.$appointment->reference.'/cancel', $data)->assertOk();
        $this->assertDatabaseCount('appointment_events', 2);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id,'status' => 'cancelled']);
        $this->assertDatabaseHas('appointment_events', ['appointment_id' => $appointment->id,'action' => 'cancel','actor_id' => $this->owner->id]);
    }
    public function test_guest_claim_requires_verified_same_number_and_preserves_patient_identity(): void
    {
        $this->withSession(['guest_identity' => ['mobile' => $this->owner->mobile,'expires_at' => now()->addMinutes(10)->timestamp]])->postJson('/api/v1/appointments', $this->bookingData($this->service))->assertCreated();
        $appointment = Appointment::sole();
        $this->actingAs($this->owner)->postJson('/api/v1/appointments/'.$appointment->reference.'/claim', $this->mutation($appointment))->assertOk();
        $this->assertSame($this->owner->id, $appointment->fresh()->booker_user_id);
        $this->assertDatabaseHas('patient_user_links', ['user_id' => $this->owner->id,'patient_id' => $appointment->patient_id]);
        $this->post('/logout');
        $this->withSession(['guest_identity' => ['mobile' => $this->owner->mobile,'expires_at' => now()->addMinutes(10)->timestamp]])->get('/appointments/'.$appointment->reference)->assertNotFound();
    }
    public function test_patient_and_booker_limits_and_rollback_of_new_patient(): void
    {
        DB::table('booking_settings')->where('id', 1)->update(['max_active_booker' => 1]);
        $appointment = $this->create();
        $this->postJson('/api/v1/appointments', $this->bookingData($this->service, $appointment->start_at->addDay()->toIso8601String()))->assertConflict()->assertJsonPath('error.code', 'BOOKING_LIMIT_REACHED');
        $this->assertDatabaseCount('patients', 1);
        DB::table('booking_settings')->where('id', 1)->update(['max_active_booker' => 10,'max_active_patient' => 1]);
        $this->postJson('/api/v1/appointments', [...$this->bookingData($this->service, $appointment->start_at->addDay()->toIso8601String()),'patient_id' => $appointment->patient_id])->assertConflict()->assertJsonPath('error.code', 'BOOKING_LIMIT_REACHED');
    }
    public function test_secretary_phone_booking_override_and_public_capacity_remains_closed(): void
    {
        $appointment = $this->create();
        $staff = $this->booker('09123456787', 'secretary');
        $data = [...$this->bookingData($this->service),'booker_mobile' => '09120000000','override_capacity' => true,'reason' => 'نوبت استثنایی'];
        $this->actingAs($staff)->postJson('/api/v1/appointments', $data)->assertCreated();
        $override = Appointment::latest('id')->first();
        $this->assertTrue($override->is_override);
        $this->assertSame($staff->id, $override->created_by);
        $this->assertNull($override->booker_user_id);
        $this->assertSame('pending_payment', $override->status);
        $this->actingAs($this->owner)->postJson('/api/v1/appointments', [...$this->bookingData($this->service),'override_capacity' => true,'reason' => 'جعلی'])->assertForbidden();
        $this->assertDatabaseCount('appointments', 2);
    }
    public function test_cutoff_staff_reason_and_expired_mutation(): void
    {
        $today = CarbonImmutable::now('Asia/Tehran')->setTime(17, 0)->utc()->toIso8601String();
        $appointment = $this->create(['start_at' => $today]);
        $this->postJson('/api/v1/appointments/'.$appointment->reference.'/cancel', $this->mutation($appointment))->assertConflict()->assertJsonPath('error.code', 'EDIT_WINDOW_CLOSED');
        $staff = $this->booker('09123456787', 'secretary');
        $this->actingAs($staff)->postJson('/api/v1/appointments/'.$appointment->reference.'/cancel', $this->mutation($appointment))->assertOk();
        $this->actingAs($this->owner);
        $next = $this->create();
        $this->travel(15)->minutes();
        $this->postJson('/api/v1/appointments/'.$next->reference.'/reschedule', $this->mutation($next, ['start_at' => $next->start_at->addDay()->toIso8601String()]))->assertConflict()->assertJsonPath('error.code', 'HOLD_EXPIRED');
    }
    public function test_paid_cancellation_fails_closed_until_p4_and_stale_version_rejected(): void
    {
        $appointment = $this->create();
        $appointment->update(['payment_status' => 'paid']);
        $this->postJson('/api/v1/appointments/'.$appointment->reference.'/cancel', $this->mutation($appointment))->assertConflict()->assertJsonPath('error.code', 'FINANCIAL_SETTLEMENT_REQUIRED');
        $this->assertSame('pending_payment', $appointment->fresh()->status);
        $this->postJson('/api/v1/appointments/'.$appointment->reference.'/reschedule', $this->mutation($appointment, ['expected_version' => 2,'start_at' => $appointment->start_at->addDay()->toIso8601String()]))->assertConflict()->assertJsonPath('error.code', 'STALE_APPOINTMENT');
    }
    public function test_settings_permissions_validation_and_csrf(): void
    {
        $this->actingAs($this->owner)->putJson('/admin/booking-settings', ['hold_minutes' => 20])->assertForbidden();
        $admin = $this->booker('09123456786', 'admin');
        $this->actingAs($admin)->put('/admin/booking-settings', ['hold_minutes' => '۲۰','cutoff_hours' => '۱۲','max_active_patient' => 3,'max_active_booker' => 4])->assertRedirect();
        $this->assertDatabaseHas('configuration_audit_events', ['action' => 'booking.settings','actor_id' => $admin->id]);
        $this->postJson('/api/v1/appointments', [...$this->bookingData($this->service),'start_at' => 'tomorrow'])->assertUnprocessable();
        $this->app->instance('env', 'local');
        $this->post('/api/v1/appointments', $this->bookingData($this->service))->assertStatus(419);
    }
    public function test_key_cannot_be_reused_for_different_appointment(): void
    {
        $first = $this->create();
        $second = $this->create(['start_at' => $first->start_at->addDay()->toIso8601String()]);
        $data = $this->mutation($first);
        $this->postJson('/api/v1/appointments/'.$first->reference.'/cancel', $data)->assertOk();
        $this->postJson('/api/v1/appointments/'.$second->reference.'/cancel', $data)->assertConflict()->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
        $this->assertSame('pending_payment', $second->fresh()->status);
    }

    public function test_real_confirmed_reader_and_schedule_warning_do_not_delete_booking(): void
    {
        $appointment = $this->create();
        $appointment->update(['status' => 'confirmed','payment_status' => 'paid','hold_expires_at' => null]);
        $admin = $this->booker('09123456785', 'admin');
        $this->actingAs($admin)->postJson('/management/schedule/exceptions', ['start_jalali' => '1405-08-06','end_jalali' => '1405-08-06','kind' => 'closed','shifts' => [],'reason' => 'تعطیلی آزمایشی'])->assertUnprocessable();
        $this->assertSame('confirmed', $appointment->fresh()->status);
        $this->assertDatabaseCount('schedule_exceptions', 0);
        $other = $this->booker('09123456784');
        $this->actingAs($other)->postJson('/api/v1/appointments', $this->bookingData($this->service))->assertConflict()->assertJsonPath('error.code', 'SLOT_UNAVAILABLE');
    }

    public function test_private_edit_calendar_excludes_only_owned_booking_and_uses_snapshot(): void
    {
        $appointment = $this->create();
        $this->service->update(['duration_minutes' => 60,'price_irr' => 2000000]);
        $url = '/api/v1/appointments/'.$appointment->reference.'/availability?service_id='.$this->service->id.'&date='.$appointment->local_date;
        $this->getJson($url)->assertOk()->assertJsonCount(28, 'data.slots')->assertJsonPath('data.slots.18.is_bookable', true);
        $other = $this->booker('09123456784');
        $this->actingAs($other)->getJson($url)->assertNotFound();
    }

    public function test_replaying_expired_creation_never_renews_hold(): void
    {
        $data = $this->bookingData($this->service);
        $this->actingAs($this->owner)->postJson('/api/v1/appointments', $data)->assertCreated();
        $expiry = Appointment::sole()->hold_expires_at->timestamp;
        $this->travel(15)->minutes();
        $this->postJson('/api/v1/appointments', $data)->assertCreated()->assertJsonPath('data.booking_status', 'expired');
        $this->assertDatabaseCount('appointments', 1);
        $this->assertSame($expiry, Appointment::sole()->hold_expires_at->timestamp);
    }

    public function test_price_change_and_past_or_outside_horizon_are_not_accepted(): void
    {
        $data = $this->bookingData($this->service);
        $this->service->update(['price_irr' => 2000000]);
        $this->actingAs($this->owner)->postJson('/api/v1/appointments', $data)->assertConflict()->assertJsonPath('error.code', 'PRICE_CHANGED');
        foreach ([now()->subDay()->toIso8601String(),now()->addDays(31)->toIso8601String()] as $start) {
            $this->postJson('/api/v1/appointments', $this->bookingData($this->service->fresh(), $start))->assertConflict()->assertJsonPath('error.code', 'SLOT_UNAVAILABLE');
        }
        $this->assertDatabaseCount('patients', 0);
    }

    public function test_disabled_identity_and_booking_rate_limit(): void
    {
        $appointment = $this->create();
        $this->owner->forceFill(['is_active' => false])->save();
        $this->get('/appointments/'.$appointment->reference)->assertForbidden();
        $this->postJson('/api/v1/appointments', $this->bookingData($this->service))->assertForbidden();
        $this->owner->forceFill(['is_active' => true])->save();
        $this->travel(61)->seconds();
        for ($i = 0;$i < 20;$i++) {
            $this->postJson('/api/v1/appointments', [])->assertUnprocessable();
        }
        $this->postJson('/api/v1/appointments', [])->assertStatus(429);
    }

    public function test_database_failure_does_not_log_or_expose_patient_bindings(): void
    {
        \Illuminate\Support\Facades\Log::spy();
        \Illuminate\Support\Facades\Event::listen('eloquent.creating: '.\App\Modules\Patients\Infrastructure\Patient::class, function (): void {
            throw new \Illuminate\Database\QueryException('mysql', 'INSERT INTO patients (name) VALUES (?)', ['PRIVATE_PATIENT_VALUE'], new \PDOException('synthetic failure'));
        });
        $this->actingAs($this->owner)->postJson('/api/v1/appointments', $this->bookingData($this->service))
            ->assertStatus(503)->assertJsonPath('error.code', 'BOOKING_TEMPORARILY_UNAVAILABLE')->assertDontSee('PRIVATE_PATIENT_VALUE');
        \Illuminate\Support\Facades\Log::shouldHaveReceived('error')->once()->with('booking.database_error', \Mockery::type('array'));
        $this->assertDatabaseCount('appointments', 0);
    }
}
