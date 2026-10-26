<?php

namespace Tests\Feature;

use App\Modules\Catalog\Infrastructure\Service;
use App\Modules\Identity\Infrastructure\User;
use App\Modules\Identity\Infrastructure\Role;
use App\Modules\Scheduling\Domain\BookingReadInterface;
use App\Modules\Scheduling\Infrastructure\ScheduleException;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeBookings;
use Tests\TestCase;

final class P2SchedulingTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;

    public function test_full_day_and_horizon_boundary_are_explicit(): void
    {
        $this->actingAs($this->admin)->post('/management/schedule/weekly', $this->weekly());
        $this->post('/management/schedule/weekly', [...$this->weekly(),'weekday' => 1]);
        $this->day('2026-11-23')->assertJsonPath('data.status', 'available');
        $this->day('2026-11-24')->assertJsonPath('data.status', 'outside_window');
        $this->app->instance(BookingReadInterface::class, new FakeBookings([['start_at' => '2026-10-28T13:30:00Z','end_at' => '2026-10-28T18:30:00Z','status' => 'confirmed']]));
        $this->day()->assertJsonPath('data.status', 'full')->assertJsonPath('data.slots.0.available_capacity', 0);
    }

    public function test_weekly_change_and_exception_removal_require_acknowledgement(): void
    {
        $this->app->instance(BookingReadInterface::class, new FakeBookings([['start_at' => '2026-10-28T13:30:00Z','end_at' => '2026-10-28T14:00:00Z','status' => 'confirmed']]));
        $this->actingAs($this->admin)->postJson('/management/schedule/weekly', $this->weekly())->assertUnprocessable();
        $this->post('/management/schedule/exceptions', [...$this->exception(),'confirm_affected' => true])->assertRedirect();
        $id = ScheduleException::sole()->id;
        $this->deleteJson('/management/schedule/exceptions/'.$id)->assertUnprocessable();
        $this->delete('/management/schedule/exceptions/'.$id, ['confirm_affected' => true])->assertRedirect();
        $this->assertDatabaseCount('schedule_exceptions', 0);
    }

    public function test_p2_mutations_also_require_csrf(): void
    {
        $this->app->instance('env', 'local');
        $this->actingAs($this->admin)->post('/admin/services', $this->serviceData())->assertStatus(419);
        $this->post('/management/schedule/weekly', $this->weekly())->assertStatus(419);
    }
    private Service $service;
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->travelTo(CarbonImmutable::parse('2026-10-25 08:00', 'Asia/Tehran'));
        $this->admin = User::create(['mobile' => '09123456789']);
        $this->admin->forceFill(['mobile_verified_at' => now()])->save();
        $this->admin->roles()->attach(Role::where('name', 'admin')->sole());
        $this->service = Service::create($this->serviceData());
    }
    private function serviceData(): array
    {
        return ['name' => 'ویزیت','price_irr' => 1000000,'duration_minutes' => 30,'buffer_minutes' => 0,'capacity' => 1,'is_active' => true,'is_online' => true];
    }
    private function weekly(array $shifts = []): array
    {
        return ['weekday' => 3,'is_closed' => false,'shifts' => $shifts ?: [['start' => '17:00','end' => '22:00','step_minutes' => 30,'capacity' => 1]]];
    }
    private function exception(string $kind = 'closed'): array
    {
        return ['start_jalali' => '1405-08-06','end_jalali' => '1405-08-06','kind' => $kind,'reason' => 'دلیل داخلی خصوصی','shifts' => $kind === 'closed' ? [] : $this->weekly()['shifts']];
    }
    private function day(string $date = '2026-10-28')
    {
        return $this->getJson('/api/v1/availability?service_id='.$this->service->id.'&date='.$date);
    }
    public function test_wednesday_acceptance_scenario_ajax_and_utc(): void
    {
        $this->actingAs($this->admin)->post('/management/schedule/weekly', $this->weekly())->assertRedirect();
        $response = $this->day()->assertOk()->assertJsonPath('meta.preview_only', true);
        $slots = $response->json('data.slots');
        $this->assertCount(10, $slots);
        $this->assertSame('21:30', $slots[9]['local_start_time']);
        $this->assertSame('2026-10-28T13:30:00+00:00', $slots[0]['start_at_utc']);
        $this->assertSame('1405-08-06', $response->json('data.jalali'));
    }
    public function test_long_service_buffer_breaks_and_no_midnight_crossing(): void
    {
        $this->actingAs($this->admin)->post('/management/schedule/weekly', $this->weekly([
            ['start' => '17:00','end' => '18:00','step_minutes' => 30,'capacity' => 1],
            ['start' => '19:00','end' => '22:00','step_minutes' => 30,'capacity' => 1],
        ]))->assertRedirect();
        $this->service->update(['duration_minutes' => 60,'buffer_minutes' => 30]);
        $slots = $this->day()->json('data.slots');
        $this->assertCount(4, $slots);
        $this->assertSame('19:00', $slots[0]['local_start_time']);
        $bad = $this->weekly([['start' => '23:00','end' => '01:00','step_minutes' => 30,'capacity' => 1]]);
        $this->postJson('/management/schedule/weekly', $bad)->assertUnprocessable();
    }
    public function test_holidays_override_weekly_and_reopening_overrides_recurring_closure(): void
    {
        $this->actingAs($this->admin)->post('/management/schedule/weekly', $this->weekly());
        $this->post('/management/schedule/exceptions', $this->exception())->assertRedirect();
        $this->day()->assertJsonPath('data.status', 'holiday')->assertJsonCount(0, 'data.slots')->assertDontSee('دلیل داخلی خصوصی');
        $id = ScheduleException::sole()->id;
        $this->delete('/management/schedule/exceptions/'.$id)->assertRedirect();
        $this->post('/management/schedule/weekly', ['weekday' => 3,'is_closed' => true,'shifts' => []]);
        $this->post('/management/schedule/exceptions', $this->exception('override'))->assertRedirect();
        $this->day()->assertJsonPath('data.status', 'available');
    }
    public function test_overlap_rejected_but_adjacent_shifts_and_date_ranges_allowed(): void
    {
        $this->actingAs($this->admin);
        $bad = $this->weekly([
            ['start' => '17:00','end' => '19:00','step_minutes' => 30,'capacity' => 1],
            ['start' => '18:00','end' => '20:00','step_minutes' => 30,'capacity' => 1],
        ]);
        $this->postJson('/management/schedule/weekly', $bad)->assertUnprocessable();
        $this->post('/management/schedule/exceptions', $this->exception())->assertRedirect();
        $this->postJson('/management/schedule/exceptions', $this->exception('override'))->assertUnprocessable();
        $this->assertDatabaseCount('schedule_exceptions', 1);
    }
    public function test_valid_holds_confirmed_and_expired_holds_affect_shared_resource(): void
    {
        $this->actingAs($this->admin)->post('/management/schedule/weekly', $this->weekly());
        $busy = new FakeBookings([
            ['start_at' => '2026-10-28T13:30:00Z','end_at' => '2026-10-28T14:30:00Z','status' => 'confirmed'],
            ['start_at' => '2026-10-28T14:30:00Z','end_at' => '2026-10-28T15:00:00Z','status' => 'pending_payment','hold_expires_at' => now()->addMinute()->toIso8601String()],
            ['start_at' => '2026-10-28T15:00:00Z','end_at' => '2026-10-28T15:30:00Z','status' => 'pending_payment','hold_expires_at' => now()->toIso8601String()],
        ]);
        $this->app->instance(BookingReadInterface::class, $busy);
        $slots = $this->day()->json('data.slots');
        $this->assertSame([false,false,false,true], array_column(array_slice($slots, 0, 4), 'is_bookable'));
        $this->travel(61)->seconds();
        $this->assertTrue($this->day()->json('data.slots.2.is_bookable'));
    }
    public function test_closing_day_with_confirmed_bookings_requires_acknowledgement(): void
    {
        $busy = new FakeBookings([['start_at' => '2026-10-28T13:30:00Z','end_at' => '2026-10-28T14:00:00Z','status' => 'confirmed']]);
        $this->app->instance(BookingReadInterface::class, $busy);
        $this->actingAs($this->admin)->postJson('/management/schedule/exceptions', $this->exception())->assertUnprocessable()->assertSee('1');
        $this->assertDatabaseCount('schedule_exceptions', 0);
        $this->post('/management/schedule/exceptions', [...$this->exception(),'confirm_affected' => true])->assertRedirect();
        $this->assertCount(1, $busy->items);
        $this->assertDatabaseHas('configuration_audit_events', ['action' => 'exception.created','actor_id' => $this->admin->id]);
    }
    public function test_horizon_past_and_full_statuses_and_month_contract(): void
    {
        $this->actingAs($this->admin)->post('/management/schedule/weekly', $this->weekly());
        $this->day('2026-10-24')->assertJsonPath('data.status', 'past');
        $this->day('2026-11-24')->assertJsonPath('data.status', 'outside_window');
        $this->put('/management/schedule/horizon', ['horizon_days' => 2])->assertRedirect();
        $this->day()->assertJsonPath('data.status', 'outside_window');
        $response = $this->getJson('/api/v1/calendar?service_id='.$this->service->id.'&month=1405-08')->assertOk();
        $this->assertCount(30, $response->json('data.days'));
        $this->assertArrayNotHasKey('slots', $response->json('data.days.0'));
    }
    public function test_disabled_offline_and_archived_services_are_hidden(): void
    {
        foreach (['is_active','is_online'] as $field) {
            $this->service->update([$field => false]);
            $this->day()->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
            $this->service->update([$field => true]);
        }
        $this->service->delete();
        $this->day()->assertNotFound();
    }
    public function test_catalog_history_soft_delete_integer_money_and_xss(): void
    {
        $this->actingAs($this->admin);
        $this->post('/admin/services', [...$this->serviceData(),'name' => '<script>bad</script>'])->assertRedirect();
        $service = Service::latest('id')->first();
        $this->get('/admin/services')->assertOk()->assertDontSee('<script>bad</script>', false)->assertSee('&lt;script&gt;bad&lt;/script&gt;', false);
        $this->put('/admin/services/'.$service->id, [...$this->serviceData(),'price_irr' => 2000000])->assertRedirect();
        $this->assertDatabaseCount('service_price_histories', 2);
        $this->putJson('/admin/services/'.$service->id, [...$this->serviceData(),'price_irr' => '1.5'])->assertUnprocessable();
        $this->delete('/admin/services/'.$service->id)->assertRedirect();
        $this->assertSoftDeleted('services', ['id' => $service->id]);
        $this->assertDatabaseCount('service_price_histories', 2);
    }
    public function test_invalid_dates_steps_capacity_and_api_errors_are_rejected(): void
    {
        $this->get('/api/v1/calendar?service_id='.$this->service->id.'&month=1405-13')->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->day('2026-02-30')->assertUnprocessable();
        $this->actingAs($this->admin)->postJson('/management/schedule/exceptions', [...$this->exception(),'start_jalali' => '1404-12-30'])->assertUnprocessable();
        $this->postJson('/management/schedule/weekly', $this->weekly([['start' => '17:00','end' => '22:00','step_minutes' => 0,'capacity' => 0]]))->assertUnprocessable();
    }
    public function test_role_matrix_for_catalog_schedule_and_settings(): void
    {
        $this->actingAs($this->admin)->get('/management/schedule')->assertOk();
        $user = User::create(['mobile' => '09123456788']);
        $user->forceFill(['mobile_verified_at' => now()])->save();
        $user->roles()->attach(Role::where('name', 'patient')->sole());
        $this->actingAs($user)->postJson('/management/schedule/weekly', $this->weekly())->assertForbidden();
        $this->postJson('/admin/services', $this->serviceData())->assertForbidden();
        $user->roles()->sync([Role::where('name', 'secretary')->sole()->id]);
        $this->post('/management/schedule/weekly', $this->weekly())->assertRedirect();
        $this->putJson('/management/schedule/horizon', ['horizon_days' => 40])->assertForbidden();
        $this->postJson('/admin/services', $this->serviceData())->assertForbidden();
    }
    public function test_public_calendar_has_inline_container_and_rate_limit(): void
    {
        $this->get('/calendar')->assertOk()->assertSee('id="inline-calendar"', false)->assertSee('calendar.js')->assertDontSee('type="date"', false);
        for ($i = 0;$i < 60;$i++) {
            $this->day()->assertOk();
        }
        $this->get('/api/v1/availability?service_id='.$this->service->id.'&date=2026-10-28')->assertStatus(429)->assertJsonPath('error.code', 'RATE_LIMITED');
    }
}
