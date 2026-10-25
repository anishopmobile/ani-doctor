<?php

namespace Tests\Feature;

use App\Modules\Booking\Application\DeskBookingService;
use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Patients\Infrastructure\Patient;
use App\Modules\Scheduling\Application\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\ClinicFixtures;
use Tests\TestCase;

final class P5ClinicTest extends TestCase
{
    use RefreshDatabase;
    use ClinicFixtures;
    private $secretary;
    private $service;
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->travelTo(CarbonImmutable::parse('2026-10-25 08:00', 'Asia/Tehran'));
        $this->secretary = $this->booker('09123456787', 'secretary');
        $this->service = $this->bookingServiceFixture($this->secretary);
        $this->actingAs($this->secretary);
    }
    private function attendance(Appointment $appointment, string $action, array $changes = [])
    {
        return $this->postJson('/management/appointments/'.$appointment->reference.'/attendance', $this->attendanceData($appointment, $action, $changes));
    }
    private function todayStart(): string
    {
        return CarbonImmutable::now('Asia/Tehran')->setTime(17, 0)->utc()->toIso8601String();
    }
    public function test_staff_dashboards_replace_placeholders_and_patient_dashboard_is_scoped(): void
    {
        $this->get('/dashboard')->assertRedirect('/secretary');
        $this->get('/secretary')->assertOk()->assertSee('میز کار مطب')->assertSee('نوبت تلفنی / فوری');
        $admin = $this->booker('09123456786', 'admin');
        $this->actingAs($admin)->get('/dashboard')->assertRedirect('/admin');
        $this->get('/admin')->assertOk();
        $this->get('/secretary')->assertForbidden();
        $appointment = $this->clinicAppointment();
        $patient = $this->booker('09120000001');
        $this->actingAs($patient)->get('/dashboard')->assertOk()->assertSee($appointment->patient->name);
        $other = $this->booker('09120000009');
        $this->actingAs($other)->get('/patient')->assertOk()->assertDontSee($appointment->patient->name);
    }
    public function test_patient_guest_inactive_and_permission_restricted_staff_cannot_use_desk_mutations(): void
    {
        $appointment = $this->clinicAppointment(['start_at' => $this->todayStart()]);
        $patient = $this->booker();
        $this->actingAs($patient)->get('/management/desk')->assertForbidden();
        $this->get('/management/bookings/new')->assertForbidden();
        $this->get('/management/patients/'.$appointment->patient_id)->assertForbidden();
        $this->attendance($appointment, 'arrived')->assertForbidden();
        $this->postJson('/management/bookings', $this->deskData())->assertForbidden();
        $this->secretary->forceFill(['is_active' => false])->save();
        $this->actingAs($this->secretary)->get('/secretary')->assertForbidden();
        $this->attendance($appointment, 'arrived')->assertForbidden();
        $this->assertDatabaseCount('attendance_events', 0);
    }
    public function test_day_filter_uses_tehran_date_and_effective_hold_expiry_without_job(): void
    {
        $appointment = $this->clinicAppointment(['start_at' => $this->todayStart()]);
        $hold = app(DeskBookingService::class)->create($this->bookingActor($this->secretary), $this->deskData(['start_at' => CarbonImmutable::parse($this->todayStart())->addHour()->toIso8601String()]));
        $hold->update(['hold_expires_at' => now()]);
        $future = $this->clinicAppointment();
        $future->update(['start_at' => CarbonImmutable::parse('2026-10-25 20:31:00', 'UTC'), 'local_date' => '2026-10-26']);
        $this->get('/management/desk')->assertOk()->assertViewHas('summary', fn ($summary) => $summary['total'] === 2 && $summary['scheduled'] === 1 && $summary['expired'] === 1 && $summary['holds'] === 0)->assertSee($appointment->reference)->assertDontSee($future->reference);
        $this->get('/management/desk?date=۱۴۰۵-۰۸-۰۴')->assertOk()->assertSee($future->reference)->assertDontSee($appointment->reference);
        $this->getJson('/management/desk?date=1405-13-01')->assertUnprocessable();
    }
    public function test_desk_search_does_not_put_name_or_mobile_in_redirect_url_and_filters_literal_values(): void
    {
        $appointment = $this->clinicAppointment(['start_at' => $this->todayStart(), 'patient_name' => 'نام خاص آزمایشی']);
        $other = $this->clinicAppointment(['start_at' => CarbonImmutable::parse($this->todayStart())->addHour()->toIso8601String(), 'patient_name' => 'نام دیگر']);
        $this->post('/management/desk/search', ['query' => 'نام خاص', 'date' => '۱۴۰۵-۰۸-۰۳'])->assertRedirect('/management/desk?date=1405-08-03');
        $this->get('/management/desk')->assertOk()->assertSee($appointment->reference)->assertDontSee($other->reference)->assertViewHas('summary', fn ($summary) => $summary['total'] === 2);
        $this->post('/management/desk/search', ['query' => '۰۹۱۲۰۰۰۰۰۰۲'])->assertRedirect('/management/desk');
        $this->get('/management/desk')->assertOk()->assertSee($other->reference)->assertDontSee($appointment->reference);
        $this->post('/management/desk/search', ['query' => '%'])->assertRedirect();
        $this->get('/management/desk')->assertViewHas('appointments', fn ($items) => $items->total() === 0);
    }
    public function test_phone_booking_without_account_can_hold_or_atomically_record_complete_clinic_payment(): void
    {
        $payload = $this->deskData(['file_number' => 'FILE-101']);
        $this->postJson('/management/bookings', $payload)->assertCreated()->assertJsonPath('data.booking_status', 'pending_payment');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('patients', ['file_number' => 'FILE-101']);
        $this->assertDatabaseHas('appointments', ['channel' => 'phone', 'created_by' => $this->secretary->id]);
        $this->assertDatabaseCount('payments', 0);
        $payload = $this->cashData(['start_at' => CarbonImmutable::parse($payload['start_at'])->addHour()->toIso8601String()]);
        $this->postJson('/management/bookings', $payload)->assertCreated()->assertJsonPath('data.booking_status', 'confirmed')->assertJsonPath('data.payment_status', 'paid')->assertJsonPath('data.balance_due_irr', 0);
        $this->postJson('/management/bookings', $payload)->assertCreated();
        $this->assertDatabaseCount('appointments', 2);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1, DB::table('financial_events')->where('action', 'payment.clinic')->count());
        $this->assertDatabaseHas('payment_settings', ['emergency_until' => null]);
    }
    public function test_partial_cash_or_unauthorized_collection_rolls_back_patient_booking_and_receipt(): void
    {
        $this->postJson('/management/bookings', $this->cashData(['received_irr' => 1]))->assertConflict();
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('patients', 0);
        $this->assertDatabaseCount('payments', 0);
        $admin = $this->booker('09123456786', 'admin');
        $this->actingAs($admin)->postJson('/management/bookings', $this->cashData())->assertForbidden();
        $this->assertDatabaseCount('appointments', 0);
    }
    public function test_phone_debt_still_requires_active_admin_emergency_and_keeps_payment_unpaid(): void
    {
        $this->postJson('/management/bookings', $this->deskData(['settlement' => 'clinic_due']))->assertConflict()->assertJsonPath('error.code', 'EMERGENCY_DISABLED');
        $this->assertDatabaseCount('patients', 0);
        DB::table('payment_settings')->where('id', 1)->update(['emergency_until' => now()->addHour()]);
        $payload = $this->deskData(['settlement' => 'clinic_due']);
        $this->postJson('/management/bookings', $payload)->assertCreated()->assertJsonPath('data.booking_status', 'confirmed')->assertJsonPath('data.payment_status', 'unpaid')->assertJsonPath('data.balance_due_irr', 1000000);
        $this->assertDatabaseCount('payments', 0);
        $this->travel(2)->hours();
        $this->postJson('/management/bookings', $payload)->assertCreated();
        $this->assertDatabaseCount('appointments', 1);
    }
    public function test_urgent_off_grid_today_honors_buffer_shift_and_never_changes_public_schedule(): void
    {
        $payload = $this->cashData(['booking_kind' => 'urgent', 'urgent_date' => '2026-10-25', 'urgent_time' => '08:01']);
        unset($payload['start_at']);
        $reference = $this->postJson('/management/bookings', $payload)->assertCreated()->assertJsonPath('data.booking_status', 'confirmed')->json('data.reference');
        $appointment = Appointment::where('reference', $reference)->sole();
        $this->assertSame('08:01', $appointment->start_at->setTimezone('Asia/Tehran')->format('H:i'));
        $this->assertSame('urgent', $appointment->channel);
        $this->assertFalse($appointment->is_override);
        $this->assertDatabaseCount('schedule_exceptions', 0);
        $date = CarbonImmutable::now('Asia/Tehran')->startOfDay();
        $slots = app(AvailabilityService::class)->days($this->service, $date, $date)[0]['slots'];
        $this->assertFalse($slots[1]['is_bookable']); // 08:30 overlaps 08:01–08:31
        $this->assertTrue($slots[2]['is_bookable']);
        $this->assertNotContains('08:01', array_column($slots, 'local_start_time'));
    }
    public function test_urgent_over_capacity_requires_explicit_permission_reason_and_does_not_open_public_capacity(): void
    {
        $this->clinicAppointment(['start_at' => $this->todayStart()]);
        $payload = $this->cashData(['booking_kind' => 'urgent', 'urgent_date' => '2026-10-25', 'urgent_time' => '17:01']);
        $this->postJson('/management/bookings', $payload)->assertConflict()->assertJsonPath('error.code', 'SLOT_UNAVAILABLE');
        $payload['override_capacity'] = true;
        $this->postJson('/management/bookings', [...$payload, 'reason' => ''])->assertUnprocessable();
        $this->postJson('/management/bookings', $payload)->assertCreated();
        $this->assertDatabaseHas('appointments', ['channel' => 'urgent', 'is_override' => true, 'override_reason' => $payload['reason']]);
        $day = CarbonImmutable::now('Asia/Tehran')->startOfDay();
        $slots = app(AvailabilityService::class)->days($this->service, $day, $day)[0]['slots'];
        $this->assertFalse(collect($slots)->firstWhere('local_start_time', '17:30')['is_bookable']);
    }
    public function test_urgent_rejects_past_other_day_closed_day_shift_end_and_missing_permission(): void
    {
        $base = $this->cashData(['booking_kind' => 'urgent', 'urgent_date' => '2026-10-25', 'urgent_time' => '08:01']);
        $this->postJson('/management/bookings', [...$base, 'urgent_time' => '07:59'])->assertConflict();
        $this->postJson('/management/bookings', [...$base, 'urgent_date' => '2026-10-26'])->assertConflict()->assertJsonPath('error.code', 'URGENT_NOT_ALLOWED');
        $this->postJson('/management/bookings', [...$base, 'urgent_time' => '21:31', 'override_capacity' => true])->assertConflict();
        app(\App\Modules\Scheduling\Application\ScheduleService::class)->saveException(['start_date' => '2026-10-25', 'end_date' => '2026-10-25', 'kind' => 'closed', 'shifts' => [], 'reason' => 'تعطیلی آزمایشی'], $this->secretary->id);
        $this->postJson('/management/bookings', [...$base, 'override_capacity' => true])->assertConflict();
        $this->assertDatabaseCount('appointments', 0);
    }
    public function test_new_and_existing_same_mobile_patients_are_never_merged_and_explicit_staff_link_is_added(): void
    {
        $first = Patient::create(['name' => 'بیمار هم‌نام', 'contact_mobile' => '09120000001']);
        $second = Patient::create(['name' => 'بیمار هم‌نام', 'contact_mobile' => '09120000001']);
        $payload = $this->deskData(['patient_id' => $second->id, 'patient_name' => 'نام ارسالی نباید بازنویسی شود', 'booker_mobile' => '09120000009']);
        $this->postJson('/management/bookings', $payload)->assertCreated();
        $this->assertDatabaseCount('patients', 2);
        $this->assertDatabaseHas('guest_patient_links', ['patient_id' => $second->id, 'mobile_hash' => hash_hmac('sha256', '09120000009', (string) config('app.key'))]);
        $this->assertDatabaseMissing('guest_patient_links', ['patient_id' => $first->id]);
        $this->assertSame('بیمار هم‌نام', $second->fresh()->name);
    }
    public function test_patient_directory_add_edit_search_more_than_old_100_limit_and_history(): void
    {
        $this->post('/management/patients', ['name' => 'بیمار مستقل', 'contact_mobile' => '۰۹۱۲۰۰۰۰۰۰۱', 'file_number' => '۱۲۳'])->assertRedirect();
        $patient = Patient::sole();
        $this->assertSame('123', $patient->file_number);
        $this->put('/management/patients/'.$patient->id, ['name' => 'نام اصلاح‌شده', 'contact_mobile' => '09120000002', 'file_number' => '123'])->assertRedirect();
        $this->postJson('/management/patients/search', ['query' => '۱۲۳'])->assertOk()->assertJsonPath('data.patients.0.id', $patient->id);
        $this->postJson('/management/patients', ['name' => 'بیمار دیگر', 'file_number' => '۱۲۳'])->assertUnprocessable();
        foreach (range(1, 105) as $index) {
            Patient::create(['name' => 'نام قبل '.$index]);
        }
        $this->postJson('/management/patients/search', ['query' => 'نام اصلاح‌شده'])->assertOk()->assertJsonCount(1, 'data.patients');
        $this->post('/management/patients/search', ['query' => '09120000002'])->assertRedirect('/management/patients');
        $this->get('/management/patients')->assertOk()->assertSee('نام اصلاح‌شده')->assertDontSee('نام قبل 1');
        $this->get('/management/patients/'.$patient->id)->assertOk()->assertSee('سابقه نوبت‌ها');
        $this->assertSame(2, DB::table('configuration_audit_events')->where('action', 'patient.saved')->count());
        $this->assertDatabaseCount('users', 1);
    }
    public function test_arrival_completion_are_audited_versioned_and_do_not_change_financial_data(): void
    {
        $appointment = $this->clinicAppointment(['start_at' => $this->todayStart()]);
        $this->attendance($appointment, 'arrived')->assertOk()->assertJsonPath('data.attendance_status', 'arrived');
        $appointment->refresh();
        $this->attendance($appointment, 'completed')->assertConflict();
        $this->travelTo($appointment->start_at);
        $this->attendance($appointment, 'completed')->assertOk()->assertJsonPath('data.booking_status', 'completed');
        $appointment->refresh();
        $this->assertNotNull($appointment->arrived_at);
        $this->assertNotNull($appointment->completed_at);
        $this->assertSame(4, $appointment->version);
        $this->assertSame('paid', $appointment->payment_status);
        $this->assertSame(0, $appointment->balance_due_irr);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('wallet_entries', 0);
        $this->assertDatabaseCount('attendance_events', 2);
        $this->attendance($appointment, 'arrived')->assertConflict();
    }
    public function test_completed_visit_keeps_original_capacity_until_buffer_end_and_frees_active_quota(): void
    {
        $this->service->update(['duration_minutes' => 60, 'buffer_minutes' => 15]);
        $appointment = $this->clinicAppointment(['start_at' => $this->todayStart()]);
        $this->attendance($appointment, 'arrived')->assertOk();
        $this->travelTo($appointment->start_at->addMinutes(5));
        $this->attendance($appointment->fresh(), 'completed')->assertOk();
        $date = $appointment->start_at->setTimezone('Asia/Tehran')->startOfDay();
        $slots = app(AvailabilityService::class)->days($this->service, $date, $date)[0]['slots'];
        $this->assertFalse(collect($slots)->firstWhere('local_start_time', '18:00')['is_bookable']);
        $this->assertTrue(collect($slots)->firstWhere('local_start_time', '18:30')['is_bookable']);
        DB::table('booking_settings')->where('id', 1)->update(['max_active_patient' => 1, 'max_active_booker' => 1]);
        $data = $this->deskData(['patient_id' => $appointment->patient_id, 'booker_mobile' => '09120000001', 'start_at' => $appointment->start_at->addDay()->toIso8601String()]);
        $this->postJson('/management/bookings', $data)->assertCreated();
    }
    public function test_no_show_requires_end_of_occupied_interval_and_does_not_refund_or_clear_debt(): void
    {
        DB::table('payment_settings')->where('id', 1)->update(['emergency_until' => now()->addDay()]);
        $appointment = app(DeskBookingService::class)->create($this->bookingActor($this->secretary), $this->deskData(['start_at' => $this->todayStart(), 'settlement' => 'clinic_due']))->fresh();
        $this->travelTo($appointment->occupied_until->subSecond());
        $this->attendance($appointment, 'no_show')->assertConflict();
        $this->travelTo($appointment->occupied_until);
        $this->attendance($appointment, 'no_show')->assertOk()->assertJsonPath('data.booking_status', 'no_show');
        $this->assertSame(1000000, $appointment->fresh()->balance_due_irr);
        $this->assertDatabaseCount('wallet_entries', 0);
        $this->postJson('/payments/'.$appointment->reference.'/collect', ['amount_irr' => 1000000, 'receipt' => (string) Str::uuid(), 'method' => 'cash', 'reason' => 'تسویه آزمایشی بدهی'])->assertOk();
        $this->assertSame('no_show', $appointment->fresh()->status);
        $this->assertSame('paid', $appointment->fresh()->payment_status);
    }
    public function test_arrival_reversion_keeps_audit_and_cancellation_or_reschedule_is_blocked_after_arrival(): void
    {
        $appointment = $this->clinicAppointment(['start_at' => $this->todayStart()]);
        $this->attendance($appointment, 'arrived')->assertOk();
        $appointment->refresh();
        $cancel = ['idempotency_key' => (string) Str::uuid(), 'expected_version' => $appointment->version, 'reason' => 'لغو آزمایشی'];
        $this->postJson('/api/v1/appointments/'.$appointment->reference.'/cancel', $cancel)->assertConflict()->assertJsonPath('error.code', 'ATTENDANCE_IN_PROGRESS');
        $this->postJson('/api/v1/appointments/'.$appointment->reference.'/reschedule', [...$cancel, 'start_at' => $appointment->start_at->addDay()->toIso8601String()])->assertConflict();
        $this->attendance($appointment, 'scheduled')->assertOk();
        $this->assertNull($appointment->fresh()->arrived_at);
        $this->assertDatabaseCount('attendance_events', 2);
        $cancel['expected_version'] = $appointment->fresh()->version;
        $this->postJson('/api/v1/appointments/'.$appointment->reference.'/cancel', $cancel)->assertOk();
        $this->assertDatabaseHas('wallet_accounts', ['balance_irr' => 1000000]);
    }
    public function test_attendance_replay_and_stale_version_and_key_collision_are_safe(): void
    {
        $appointment = $this->clinicAppointment(['start_at' => $this->todayStart()]);
        $data = $this->attendanceData($appointment, 'arrived');
        $url = '/management/appointments/'.$appointment->reference.'/attendance';
        $this->postJson($url, $data)->assertOk();
        $this->postJson($url, $data)->assertOk();
        $this->postJson($url, [...$data, 'action' => 'scheduled'])->assertConflict()->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
        $this->postJson($url, [...$data, 'idempotency_key' => (string) Str::uuid()])->assertConflict()->assertJsonPath('error.code', 'STALE_APPOINTMENT');
        $this->assertDatabaseCount('attendance_events', 1);
    }
    public function test_pending_cancelled_future_day_and_invalid_action_cannot_record_attendance(): void
    {
        $future = $this->clinicAppointment();
        $this->attendance($future, 'arrived')->assertConflict();
        $this->attendance($future, 'no_show')->assertConflict();
        $this->attendance($future, 'completed')->assertConflict();
        $this->attendance($future, 'paid')->assertUnprocessable();
        $this->attendance($future, 'arrived', ['reason' => ''])->assertUnprocessable();
        $hold = app(DeskBookingService::class)->create($this->bookingActor($this->secretary), $this->deskData(['start_at' => $this->todayStart()]));
        $this->attendance($hold, 'arrived')->assertConflict();
        $this->assertDatabaseCount('attendance_events', 0);
    }
    public function test_attendance_details_and_staff_notes_do_not_leak_to_patient_or_public_calendar(): void
    {
        $appointment = $this->clinicAppointment(['start_at' => $this->todayStart()]);
        $this->attendance($appointment, 'arrived', ['reason' => 'PRIVATE-OPERATOR-NOTE'])->assertOk();
        $this->get('/appointments/'.$appointment->reference)->assertOk()->assertSee('PRIVATE-OPERATOR-NOTE');
        $patient = $this->booker('09120000001');
        $this->actingAs($patient)->get('/appointments/'.$appointment->reference)->assertOk()->assertSee('مراجعه کرده')->assertDontSee('PRIVATE-OPERATOR-NOTE');
        $this->getJson('/api/v1/availability?service_id='.$this->service->id.'&date=2026-10-25')->assertOk()->assertDontSee($appointment->patient->name)->assertDontSee('PRIVATE-OPERATOR-NOTE');
    }
    public function test_new_endpoints_require_csrf_and_escape_patient_names(): void
    {
        $this->post('/management/patients', ['name' => '<script>BAD</script>'])->assertRedirect();
        $this->get('/management/patients')->assertOk()->assertDontSee('<script>BAD</script>', false)->assertSee('&lt;script&gt;BAD&lt;/script&gt;', false);
        $this->app->instance('env', 'local');
        $this->post('/management/bookings', $this->deskData())->assertStatus(419);
        $this->post('/management/patients/search', ['query' => 'test'])->assertStatus(419);
        $this->post('/management/appointments/'.Str::uuid().'/attendance', [])->assertStatus(419);
    }
    public function test_malformed_urgent_input_is_validation_error_and_not_a_server_exception(): void
    {
        $payload = $this->cashData(['booking_kind' => 'urgent', 'urgent_date' => '2026-10-25', 'urgent_time' => '08:01']);
        foreach (['2026-99-99', '2026-02-31', ['2026-10-25']] as $date) {
            $this->postJson('/management/bookings', [...$payload, 'urgent_date' => $date])->assertUnprocessable();
        }
        $this->postJson('/management/bookings', [...$payload, 'urgent_time' => ['08:01']])->assertUnprocessable();
        $this->postJson('/management/bookings', [...$payload, 'received_irr' => [1000000]])->assertUnprocessable();
        $this->assertDatabaseCount('appointments', 0);
    }
    public function test_separate_urgent_override_and_attendance_permissions_are_enforced_for_staff(): void
    {
        $role = $this->secretary->roles()->firstOrFail();
        $permission = \App\Modules\Identity\Infrastructure\Permission::where('name', 'appointments.urgent')->sole();
        $role->permissions()->detach($permission->id);
        $payload = $this->cashData(['booking_kind' => 'urgent', 'urgent_date' => '2026-10-25', 'urgent_time' => '08:01']);
        $this->postJson('/management/bookings', $payload)->assertConflict()->assertJsonPath('error.code', 'URGENT_NOT_ALLOWED');
        $appointment = $this->clinicAppointment(['start_at' => $this->todayStart()]);
        $role->permissions()->detach(\App\Modules\Identity\Infrastructure\Permission::where('name', 'attendance.manage')->sole()->id);
        $this->attendance($appointment, 'arrived')->assertForbidden();
        $role->permissions()->detach(\App\Modules\Identity\Infrastructure\Permission::where('name', 'appointments.override_capacity')->sole()->id);
        $this->postJson('/management/bookings', $this->deskData(['override_capacity' => true]))->assertForbidden();
        $this->assertDatabaseCount('attendance_events', 0);
    }
    public function test_guest_proof_cannot_open_staff_directory_or_attendance_api(): void
    {
        $this->post('/logout')->assertRedirect('/login');
        $this->withSession(['guest_identity' => ['mobile' => '09120000001', 'expires_at' => now()->addMinutes(10)->timestamp]])->get('/management/desk')->assertRedirect('/login');
        $this->postJson('/management/patients/search', ['query' => 'بیمار'])->assertUnauthorized();
        $this->postJson('/management/appointments/'.Str::uuid().'/attendance', [])->assertUnauthorized();
    }
    public function test_attendance_event_and_projection_rollback_together_and_hide_sql_bindings(): void
    {
        $appointment = $this->clinicAppointment(['start_at' => $this->todayStart()]);
        \Illuminate\Support\Facades\Log::spy();
        Appointment::updating(function ($model): void {
            if ($model->attendance_status === 'arrived') {
                throw new \Illuminate\Database\QueryException('mysql', 'UPDATE appointments SET attendance_status = ?', ['PRIVATE_ATTENDANCE_BINDING'], new \PDOException('synthetic failure'));
            }
        });
        $this->attendance($appointment, 'arrived')->assertStatus(503)->assertDontSee('PRIVATE_ATTENDANCE_BINDING');
        $this->assertSame('scheduled', $appointment->fresh()->attendance_status);
        $this->assertSame(2, $appointment->fresh()->version);
        $this->assertDatabaseCount('attendance_events', 0);
        $this->assertDatabaseCount('payments', 1);
        \Illuminate\Support\Facades\Log::shouldHaveReceived('error')->once()->with('booking.database_error', \Mockery::type('array'));
    }
    public function test_paid_phone_replay_after_cancellation_never_reconfirms_or_collects_again(): void
    {
        $payload = $this->cashData();
        $reference = $this->postJson('/management/bookings', $payload)->assertCreated()->json('data.reference');
        $appointment = Appointment::where('reference', $reference)->sole();
        $this->postJson('/api/v1/appointments/'.$reference.'/cancel', ['idempotency_key' => (string) Str::uuid(), 'expected_version' => 2, 'reason' => 'لغو آزمایشی'])->assertOk();
        $this->postJson('/management/bookings', $payload)->assertCreated()->assertJsonPath('data.booking_status', 'cancelled');
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1, DB::table('wallet_entries')->where('kind', 'cancellation')->count());
        $this->assertSame('cancelled', $appointment->fresh()->status);
    }
    public function test_valid_phone_hold_can_be_settled_in_full_at_clinic_without_emergency_and_receipt_replays(): void
    {
        $hold = app(DeskBookingService::class)->create($this->bookingActor($this->secretary), $this->deskData());
        $payload = ['amount_irr' => 1000000, 'receipt' => (string) Str::uuid(), 'reason' => 'تسویه ساختگی حضوری', 'method' => 'cash'];
        $url = '/management/appointments/'.$hold->reference.'/collect-hold';
        $this->postJson($url, [...$payload, 'amount_irr' => 1])->assertConflict();
        $this->assertSame('pending_payment', $hold->fresh()->status);
        $this->postJson($url, $payload)->assertOk();
        $this->postJson($url, $payload)->assertOk();
        $this->assertSame('confirmed', $hold->fresh()->status);
        $this->assertSame('paid', $hold->fresh()->payment_status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payment_settings', ['emergency_until' => null]);
    }
    public function test_expired_phone_hold_or_open_gateway_attempt_cannot_be_settled_manually(): void
    {
        $hold = app(DeskBookingService::class)->create($this->bookingActor($this->secretary), $this->deskData());
        $payload = ['amount_irr' => 1000000, 'receipt' => (string) Str::uuid(), 'reason' => 'تسویه ساختگی', 'method' => 'cash'];
        $this->app->instance(\App\Modules\Payments\Domain\PaymentGatewayInterface::class, new \Tests\Support\FakePaymentGateway());
        config(['payments.zarinpal' => ['enabled' => true, 'merchant_id' => '00000000-0000-4000-8000-000000000000', 'sandbox' => true, 'callback_url' => 'https://clinic.example/payments/zarinpal/callback']]);
        app(\App\Modules\Payments\Application\PaymentService::class)->initiate($this->bookingActor($this->secretary), $hold, ['idempotency_key' => (string) Str::uuid(), 'credit_irr' => 0, 'expected_version' => 1]);
        $url = '/management/appointments/'.$hold->reference.'/collect-hold';
        $this->postJson($url, $payload)->assertConflict()->assertJsonPath('error.code', 'PAYMENT_IN_PROGRESS');
        $this->travel(16)->minutes();
        $this->postJson($url, $payload)->assertConflict();
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('expired', $hold->fresh()->effectiveStatus());
    }
}
