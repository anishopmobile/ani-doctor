<?php

namespace Tests\Feature;

use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Scheduling\Application\JalaliDate;
use App\Modules\Scheduling\Application\ScheduleService;
use App\Modules\Scheduling\Application\WeeklyAnnouncementService;
use App\Modules\Scheduling\Infrastructure\WeeklyAnnouncement;
use App\Modules\Scheduling\Infrastructure\WeeklySchedule;
use App\Modules\Website\Application\ThemeService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

final class WeeklyAnnouncementTest extends TestCase
{
    use RefreshDatabase;
    use BookingFixtures;

    private $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->travelTo(CarbonImmutable::parse('2026-10-25 08:00', 'Asia/Tehran'));
        $this->admin = $this->booker('09123456789', 'admin');
    }

    private function payload(array $changes = []): array
    {
        return array_replace(['date_jalali' => '1405-08-03', 'expected_version' => 0, 'mode' => 'manual', 'status' => 'working', 'shifts' => [['start' => '16:00', 'end' => '20:00', 'label' => 'عصر']], 'location' => 'روستای علی‌آباد', 'outside_office' => true, 'notes' => 'ویزیت رایگان'], $changes);
    }

    private function save(array $changes = [])
    {
        return $this->actingAs($this->admin)->put('/management/weekly-schedule/day', $this->payload($changes));
    }

    private function day(): array
    {
        $date = CarbonImmutable::parse('2026-10-25', 'Asia/Tehran');
        return app(WeeklyAnnouncementService::class)->range($date, $date)[0];
    }

    private function source(bool $closed = false, int $start = 1020): void
    {
        app(ScheduleService::class)->saveWeekly(['weekday' => 7, 'is_closed' => $closed, 'shifts' => $closed ? [] : [['start_minute' => $start, 'end_minute' => 1320, 'step_minutes' => 30, 'capacity' => 1]]], $this->admin->id);
    }

    private function enable(string $notes = ''): void
    {
        app(ThemeService::class)->save(['show_weekly_schedule' => true, 'weekly_schedule_notes' => $notes]);
    }

    public function test_source_projection_is_live_bounded_and_idempotent_without_writes(): void
    {
        $this->assertSame('unknown', $this->day()['presence']['status']);
        $this->source();
        $this->assertSame('17:00', $this->day()['presence']['shifts'][0]['start']);
        $this->day();
        $this->assertDatabaseCount('weekly_announcements', 0);
        $this->save(['mode' => 'source', 'shifts' => []])->assertRedirect();
        $this->source(false, 1080);
        $this->assertSame('18:00', $this->day()['presence']['shifts'][0]['start']);
        $this->assertSame('روستای علی‌آباد', $this->day()['location']);
        $this->save(['mode' => 'source', 'expected_version' => 1])->assertRedirect();
        $this->assertDatabaseCount('weekly_announcements', 1);
        DB::enableQueryLog();
        app(WeeklyAnnouncementService::class)->week();
        $this->assertLessThanOrEqual(6, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    public function test_manual_presence_survives_changed_and_deleted_source_and_reset_preserves_location(): void
    {
        $this->source();
        $this->save()->assertRedirect();
        $this->source(true);
        $this->assertTrue($this->day()['source_changed']);
        $this->assertSame('working', $this->day()['presence']['status']);
        $this->assertSame('16:00', $this->day()['presence']['shifts'][0]['start']);
        WeeklySchedule::where('weekday', 7)->delete();
        $this->assertSame('unknown', $this->day()['source']['status']);
        $this->actingAs($this->admin)->get('/management/weekly-schedule')->assertOk()->assertSee('برنامه کاری تغییر کرده است');
        $this->save(['mode' => 'source', 'expected_version' => 1])->assertRedirect();
        $this->assertFalse($this->day()['manual']);
        $this->assertSame('unknown', $this->day()['presence']['status']);
        $this->assertSame('روستای علی‌آباد', $this->day()['location']);
    }

    public function test_work_schedule_exception_is_used_without_disclosing_internal_reason(): void
    {
        $this->source();
        app(ScheduleService::class)->saveException(['start_date' => '2026-10-25', 'end_date' => '2026-10-25', 'kind' => 'closed', 'shifts' => [], 'reason' => 'INTERNAL-PRIVATE-REASON'], $this->admin->id);
        $this->enable();
        $this->assertSame('closed', $this->day()['presence']['status']);
        $this->get('/weekly-schedule')->assertOk()->assertDontSee('INTERNAL-PRIVATE-REASON');
    }

    public function test_staff_permissions_guest_denial_and_stale_edit_protection(): void
    {
        $this->get('/management/weekly-schedule')->assertRedirect('/login');
        $patient = $this->booker('09120000001');
        $this->actingAs($patient)->put('/management/weekly-schedule/day', $this->payload())->assertForbidden();
        $this->get('/management/weekly-schedule')->assertForbidden();
        $this->put('/management/weekly-schedule/settings', ['show_weekly_schedule' => true, 'expected_settings_version' => 0])->assertForbidden();
        $secretary = $this->booker('09120000002', 'secretary');
        $this->actingAs($secretary)->get('/management/weekly-schedule')->assertOk();
        $this->put('/management/weekly-schedule/day', $this->payload())->assertRedirect();
        $this->put('/management/weekly-schedule/day', $this->payload(['location' => 'بازنویسی نامجاز']))->assertSessionHasErrors('expected_version');
        $this->assertDatabaseCount('weekly_announcements', 1);
        $this->assertSame('روستای علی‌آباد', $this->day()['location']);
    }

    public function test_invalid_dates_times_status_location_and_overlap_are_rejected(): void
    {
        foreach ([['date_jalali' => '1405-12-30'], ['date_jalali' => ['bad']], ['status' => 'invented'], ['location' => str_repeat('a', 201)], ['shifts' => []], ['shifts' => [['start' => '20:00', 'end' => '16:00']]], ['shifts' => [['start' => '24:00', 'end' => '24:00']]], ['shifts' => [['start' => '16:00', 'end' => '18:00'], ['start' => '17:00', 'end' => '20:00']]]] as $changes) {
            $this->actingAs($this->admin)->putJson('/management/weekly-schedule/day', $this->payload($changes))->assertUnprocessable();
        }
        $this->assertDatabaseCount('weekly_announcements', 0);
        $this->get('/management/weekly-schedule?week=1405-13-01')->assertSessionHasErrors('week');
        $this->save(['shifts' => [['start' => '۲۳:۰۰', 'end' => '۲۴:۰۰']]])->assertRedirect();
        $this->assertSame('24:00', $this->day()['presence']['shifts'][0]['end']);
    }

    public function test_closed_status_clears_hours_but_preserves_location_for_reopening(): void
    {
        $this->save()->assertRedirect();
        $this->save(['expected_version' => 1, 'status' => 'official_holiday'])->assertRedirect();
        $this->assertSame([], $this->day()['presence']['shifts']);
        $this->enable();
        $this->get('/weekly-schedule')->assertOk()->assertSee('تعطیلی رسمی')->assertDontSee('16:00')->assertDontSee('روستای علی‌آباد');
        $this->save(['expected_version' => 2, 'status' => 'closed'])->assertRedirect();
        $this->get('/weekly-schedule')->assertOk()->assertSee('تعطیل؛ پزشک حضور ندارد');
    }

    public function test_settings_are_persistent_independent_and_public_route_is_disabled_by_default(): void
    {
        $this->get('/')->assertOk()->assertDontSee('href="http://localhost/weekly-schedule"', false);
        $this->get('/weekly-schedule')->assertNotFound();
        $this->actingAs($this->admin)->put('/management/weekly-schedule/settings', ['show_weekly_schedule' => true, 'weekly_schedule_notes' => 'اطلاع‌رسانی مطب', 'expected_settings_version' => 0])->assertRedirect();
        $this->get('/')->assertOk()->assertSee(route('weekly-announcements.public'));
        $this->get('/weekly-schedule')->assertOk()->assertSee('اطلاع‌رسانی مطب')->assertSee('برنامه‌ای اعلام نشده است');
        $this->put('/management/weekly-schedule/settings', ['show_weekly_schedule' => false, 'expected_settings_version' => 0])->assertSessionHasErrors('expected_settings_version');
        $this->put('/management/weekly-schedule/settings', ['show_weekly_schedule' => false, 'expected_settings_version' => 1])->assertRedirect();
        $this->get('/weekly-schedule')->assertNotFound();
        $this->assertFalse(app(ThemeService::class)->settings()['show_weekly_schedule']);
    }

    public function test_public_dates_today_locations_and_text_are_escaped_without_empty_notes_box(): void
    {
        $this->save(['location' => '<script>alert(1)</script>', 'notes' => '<img src=x onerror=alert(2)>'])->assertRedirect();
        $this->enable();
        $this->get('/weekly-schedule')->assertOk()->assertSee('1405-08-03')->assertSee('یکشنبه')->assertSee('aria-current="date"', false)->assertSee('ویزیت خارج از مطب')->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x onerror=alert(2)>', false)->assertDontSee('class="weekly-general-notes"', false);
        $this->enable('<script>bad()</script>');
        $this->get('/weekly-schedule')->assertOk()->assertSee('<script>bad()</script>')->assertDontSee('<script>bad()</script>', false);
        $this->assertSame('1405-08-03', app(JalaliDate::class)->format(CarbonImmutable::now('Asia/Tehran')));
    }

    public function test_week_navigation_and_validation_errors_restore_only_the_failed_day(): void
    {
        $this->save(['shifts' => [['start' => 'xx', 'end' => '20:00']], '_ux_context' => 'announcement-2026-10-25'])->assertSessionHasErrors('shifts.0.start');
        $this->get('/management/weekly-schedule')->assertOk()->assertSee('announcement-2026-10-25" data-record-panel  open', false)->assertSee('value="xx"', false);
        $week = app(WeeklyAnnouncementService::class)->week('1405-08-03');
        $this->assertSame('شنبه', $week['days'][0]['weekday']);
        $this->assertCount(7, $week['days']);
        $this->assertSame('جمعه', $week['days'][6]['weekday']);
        $this->get('/management/weekly-schedule?week='.$week['next'])->assertOk()->assertSee($week['next']);
    }

    public function test_announcements_cannot_open_booking_or_modify_existing_appointments(): void
    {
        $owner = $this->booker('09120000003');
        $service = $this->bookingServiceFixture($this->admin);
        $data = $this->bookingData($service);
        $this->actingAs($owner)->postJson('/api/v1/appointments', $data)->assertCreated();
        $appointment = Appointment::sole()->getAttributes();
        $this->source(true);
        $before = DB::table('weekly_schedules')->get()->toJson().DB::table('work_shifts')->get()->toJson().DB::table('schedule_state')->get()->toJson();
        $url = '/api/v1/availability?service_id='.$service->id.'&date=2026-10-25';
        $closed = $this->getJson($url)->assertOk()->json('data');
        $this->save()->assertRedirect();
        $this->assertSame($closed, $this->getJson($url)->assertOk()->json('data'));
        $this->assertSame([], $closed['slots']);
        $data = $this->bookingData($service, CarbonImmutable::parse('2026-10-25 16:00', 'Asia/Tehran')->utc()->toIso8601String());
        $this->actingAs($owner)->postJson('/api/v1/appointments', $data)->assertStatus(409);
        $this->assertSame($before, DB::table('weekly_schedules')->get()->toJson().DB::table('work_shifts')->get()->toJson().DB::table('schedule_state')->get()->toJson());
        $this->assertSame($appointment, Appointment::sole()->getAttributes());
        $this->source();
        $bookable = $this->getJson($url)->assertOk()->json('data');
        $this->save(['expected_version' => 1, 'shifts' => [['start' => '09:00', 'end' => '10:00']]])->assertRedirect();
        $this->enable();
        $this->assertSame($bookable, $this->getJson($url)->assertOk()->json('data'));
        $this->actingAs($this->admin)->put('/management/weekly-schedule/settings', ['show_weekly_schedule' => false, 'expected_settings_version' => 1])->assertRedirect();
        $this->assertSame($bookable, $this->getJson($url)->assertOk()->json('data'));
        WeeklyAnnouncement::query()->delete();
        $this->assertSame($appointment, Appointment::sole()->getAttributes());
    }

    public function test_mutation_requires_csrf(): void
    {
        $this->app->instance('env', 'local');
        $this->save()->assertStatus(419);
        $this->put('/management/weekly-schedule/settings', ['show_weekly_schedule' => true, 'expected_settings_version' => 0])->assertStatus(419);
    }

    public function test_custom_navigation_cannot_bypass_the_weekly_visibility_switch_or_duplicate_it(): void
    {
        \App\Modules\Website\Infrastructure\WebsiteLink::create(['placement' => 'header', 'label' => 'برنامه سفارشی', 'url' => '/weekly-schedule', 'is_published' => true, 'new_tab' => false, 'sort_order' => 0]);
        $this->get('/')->assertOk()->assertDontSee(route('weekly-announcements.public'));
        $this->enable();
        $response = $this->get('/')->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'href="'.route('weekly-announcements.public').'"'));
    }

    public function test_announcement_migration_rollback_and_reapply_preserve_working_schedule(): void
    {
        $this->source();
        $this->save()->assertRedirect();
        $before = DB::table('weekly_schedules')->get()->toJson().DB::table('work_shifts')->get()->toJson();
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Homepage contact inbox.
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('website_contact_messages'));
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful(); // Optional public article author.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('weekly_announcements'));
        $this->assertSame($before, DB::table('weekly_schedules')->get()->toJson().DB::table('work_shifts')->get()->toJson());
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertDatabaseCount('weekly_announcements', 0);
        $this->assertSame($before, DB::table('weekly_schedules')->get()->toJson().DB::table('work_shifts')->get()->toJson());
    }

    public function test_unrelated_malformed_fields_do_not_enter_another_form_validation_path(): void
    {
        $this->actingAs($this->admin)->get('/management/weekly-schedule?date_jalali[]=bad&mode=manual&status=working')->assertOk();
        $this->put('/management/weekly-schedule/settings', ['show_weekly_schedule' => false, 'expected_settings_version' => 0, 'date_jalali' => ['bad']])->assertRedirect()->assertSessionHasNoErrors();
        $this->save(['week' => ['bad']])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_public_poster_preserves_multiple_shifts_dates_and_isolates_styles_from_management(): void
    {
        $this->save(['shifts' => [['start' => '08:00', 'end' => '10:00', 'label' => 'صبح'], ['start' => '16:00', 'end' => '20:00', 'label' => 'عصر']]])->assertRedirect();
        $this->enable();
        $response = $this->get('/weekly-schedule')->assertOk()->assertSee('08:00')->assertSee('10:00')->assertSee('16:00')->assertSee('20:00')->assertSee('صبح')->assertSee('عصر')->assertSee('assets/weekly-poster.css')->assertDontSee('assets/weekly-schedule.css');
        $document = new \DOMDocument();
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $headers = $xpath->query('//article[contains(@class,"weekly-poster__row")]/header');
        $this->assertCount(7, $headers);
        foreach ($headers as $header) {
            $this->assertSame('h2', $header->firstElementChild->nodeName);
            $this->assertSame('time', $header->firstElementChild->nextElementSibling->nodeName);
            $this->assertMatchesRegularExpression('/^1[34]\d{2}-\d{2}-\d{2}$/', trim($header->firstElementChild->nextElementSibling->textContent));
        }
        $this->actingAs($this->admin)->get('/management/weekly-schedule')->assertOk()->assertSee('assets/weekly-schedule.css')->assertDontSee('assets/weekly-poster.css');
        $this->get('/')->assertOk()->assertDontSee('assets/weekly-poster.css');
    }
}
