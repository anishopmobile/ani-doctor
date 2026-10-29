<?php

namespace Tests\Feature;

use App\Modules\Catalog\Infrastructure\Service;
use App\Modules\Website\Application\PageService;
use App\Modules\Website\Application\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

final class InformationPagesTest extends TestCase
{
    use RefreshDatabase;
    use BookingFixtures;

    public function test_about_contact_and_services_render_stored_data_with_isolated_css(): void
    {
        app(ThemeService::class)->save(['brand_name' => 'پزشک آزمون', 'about_text' => 'معرفی ذخیره‌شده پزشک', 'contact_address' => 'نشانی واقعی تنظیمات', 'contact_phone' => '09123456789', 'contact_email' => 'clinic@example.test', 'contact_map_url' => 'https://maps.example.test/clinic']);
        $this->get('/about')->assertOk()->assertSee('معرفی ذخیره‌شده پزشک')->assertSee('پزشک آزمون')->assertSee('information-pages.css')->assertDontSee('unsplash.com');
        $this->get('/contact')->assertOk()->assertSee('نشانی واقعی تنظیمات')->assertSee('mailto:clinic@example.test', false)->assertSee('https://maps.example.test/clinic')->assertDontSee('name="fullname"', false);
        $service = Service::create(['name' => 'خدمت واقعی تنظیمات', 'price_irr' => 1234000, 'duration_minutes' => 45, 'buffer_minutes' => 0, 'capacity' => 1, 'is_active' => true, 'is_online' => true]);
        $this->get('/services')->assertOk()->assertSee($service->name)->assertSee('1,234,000')->assertSee('45')->assertSee(route('calendar', ['service_id' => $service->id]));
        foreach (['/', '/calendar', '/weekly-schedule'] as $url) {
            $this->get($url)->assertDontSee('assets/information-pages.css');
        }
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_policy_values_follow_current_configuration_without_mutation(): void
    {
        DB::table('booking_settings')->where('id', 1)->update(['hold_minutes' => 11, 'cutoff_hours' => 37]);
        DB::table('schedule_state')->where('id', 1)->update(['horizon_days' => 46]);
        $before = DB::table('booking_settings')->get()->toJson().DB::table('schedule_state')->get()->toJson();
        $this->get('/policies')->assertOk()->assertSee('46 روز')->assertSee('11 دقیقه')->assertSee('37 ساعت')->assertSee('policy-cancel');
        DB::table('booking_settings')->where('id', 1)->update(['hold_minutes' => 18]);
        $this->get('/policies')->assertOk()->assertSee('18 دقیقه')->assertDontSee('11 دقیقه');
        DB::table('booking_settings')->where('id', 1)->update(['hold_minutes' => 11]);
        $this->assertSame($before, DB::table('booking_settings')->get()->toJson().DB::table('schedule_state')->get()->toJson());
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_only_published_cms_policy_and_privacy_content_is_exposed_and_escaped(): void
    {
        $this->seed();
        $actor = $this->booker('09120000123', 'admin');
        $pages = app(PageService::class);
        foreach (['privacy', 'policies'] as $slug) {
            $page = $pages->create(['slug' => $slug, 'title' => 'عنوان مصوب '.$slug], $actor->id);
            $page = $pages->save($page, ['expected_version' => $page->version, 'title' => $page->title, 'blocks' => [['id' => (string) Str::uuid(), 'type' => 'text', 'heading' => 'موضوع تأییدشده', 'text' => '<script>PUBLIC-TEXT</script>', 'layout' => 'stacked', 'background' => 'plain']]], $actor->id);
            $this->get('/'.$slug)->assertOk()->assertDontSee('PUBLIC-TEXT');
            $page = $pages->publication($page, $page->version, $actor->id, true);
            $this->get('/'.$slug)->assertOk()->assertSee('PUBLIC-TEXT')->assertDontSee('<script>PUBLIC-TEXT</script>', false)->assertSee('موضوع تأییدشده');
            $pages->save($page, ['expected_version' => $page->version, 'title' => 'PRIVATE-DRAFT', 'blocks' => $page->blocks], $actor->id);
            $this->get('/'.$slug)->assertOk()->assertDontSee('PRIVATE-DRAFT')->assertSee('عنوان مصوب');
            $pages->publication($page->fresh(), $page->fresh()->version, $actor->id, false);
            $this->get('/'.$slug)->assertOk()->assertDontSee('PUBLIC-TEXT');
        }
    }

    public function test_missing_privacy_content_and_optional_contact_fields_are_honest_and_safe(): void
    {
        $this->get('/privacy')->assertOk()->assertSee('راهنمای اطلاعات سامانه')->assertSee('privacy-retention')->assertDontSee('HTTPS')->assertDontSee('مدت‌های دقیق');
        app(ThemeService::class)->save(['contact_email' => '', 'contact_map_url' => '', 'contact_hours' => '', 'about_text' => '<img src=x onerror=alert(1)>']);
        $this->get('/contact')->assertOk()->assertDontSee('mailto:')->assertDontSee('مشاهده نقشه و مسیریابی')->assertDontSee('name="message"', false);
        $this->get('/about')->assertOk()->assertSee('<img src=x onerror=alert(1)>')->assertDontSee('<img src=x onerror=alert(1)>', false);
        $this->get('/sitemap.xml')->assertOk()->assertSee(route('website.privacy'))->assertSee(route('website.policies'));
    }
}
