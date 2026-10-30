<?php

namespace Tests\Feature;

use App\Modules\Catalog\Infrastructure\Service;
use App\Modules\Website\Application\ThemeService;
use App\Modules\Website\Infrastructure\WebsiteLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

final class WebsiteFooterTest extends TestCase
{
    use RefreshDatabase;
    use BookingFixtures;

    private function link(string $placement, string $label, string $url, bool $published = true): WebsiteLink
    {
        return WebsiteLink::create(['placement' => $placement, 'label' => $label, 'url' => $url, 'is_published' => $published, 'new_tab' => true, 'sort_order' => 0]);
    }

    public function test_footer_uses_real_brand_contacts_services_and_published_links(): void
    {
        app(ThemeService::class)->save(['brand_name' => 'برند ثبت‌شده', 'footer_text' => 'معرفی واقعی فوتر', 'contact_email' => 'clinic@example.test', 'contact_secondary_phone' => '02112345678', 'contact_hours' => 'ساعات ثبت‌شده پاسخ‌گویی', 'footer_booking_text' => 'توضیح ثبت‌شده رزرو', 'footer_slogan' => 'شعار ثبت‌شده', 'footer_features' => [['title' => 'ویژگی ثبت‌شده', 'text' => 'توضیح ویژگی']]]);
        $active = Service::create(['name' => 'خدمت واقعی فوتر', 'price_irr' => 100000, 'duration_minutes' => 30, 'buffer_minutes' => 0, 'capacity' => 1, 'is_active' => true, 'is_online' => true]);
        Service::create(['name' => 'خدمت غیرفعال پنهان', 'price_irr' => 100000, 'duration_minutes' => 30, 'buffer_minutes' => 0, 'capacity' => 1, 'is_active' => false, 'is_online' => true]);
        $this->link('social', 'اینستاگرام واقعی', 'https://instagram.com/clinic');
        $this->link('social', 'اجتماعی پیش‌نویس', 'https://t.me/private', false);
        $this->link('footer', 'پیوند سفارشی واقعی', '/about');
        $this->link('footer_legal', 'قانون سفارشی واقعی', '/policies');
        $this->get('/about')->assertOk()->assertSee('معرفی واقعی فوتر')->assertSee('توضیح ثبت‌شده رزرو')->assertSee('شعار ثبت‌شده')->assertSee('ویژگی ثبت‌شده')->assertSee('ساعات ثبت‌شده پاسخ‌گویی')->assertSee('mailto:clinic@example.test', false)->assertSee('tel:02112345678', false)->assertSee('خدمت واقعی فوتر')->assertSee(route('website.services.show', $active))->assertSee('اینستاگرام واقعی')->assertSee('پیوند سفارشی واقعی')->assertSee('قانون سفارشی واقعی')->assertDontSee('اجتماعی پیش‌نویس')->assertDontSee('خدمت غیرفعال پنهان')->assertDontSee('fonts.googleapis.com')->assertDontSee('cdn.jsdelivr.net');
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_optional_empty_content_and_unknown_sample_routes_are_not_fabricated(): void
    {
        app(ThemeService::class)->save(['footer_booking_text' => '', 'footer_slogan' => '', 'contact_email' => '', 'contact_hours' => '', 'contact_secondary_phone' => '', 'footer_features' => []]);
        $this->get('/')->assertOk()->assertDontSee('class="af-social"', false)->assertDontSee('class="af-hours"', false)->assertDontSee('class="af-slogan"', false)->assertDontSee('class="af-feature"', false)->assertDontSee('href="/booking"', false)->assertDontSee('href="/reviews"', false)->assertDontSee('[شماره')->assertSee(route('website.privacy'))->assertSee(route('website.policies'))->assertSee(route('website.sitemap'))->assertSee('href="#public-site-top"', false)->assertSee('id="public-site-top"', false);
    }

    public function test_authorized_staff_manage_social_and_legal_links_and_reject_unsafe_urls(): void
    {
        $this->seed();
        $payload = ['placement' => 'social', 'label' => 'شبکه واقعی', 'url' => 'https://youtube.com/@clinic', 'sort_order' => 0, 'is_published' => 1, 'new_tab' => 1];
        $this->actingAs($this->booker('09120000110'))->post('/admin/website-links', $payload)->assertForbidden();
        $this->actingAs($this->booker('09120000111', 'admin'))->post('/admin/website-links', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('website_links', ['placement' => 'social', 'url' => $payload['url']]);
        foreach (['/about', '//evil.example', 'javascript:alert(1)', 'https://user:pass@example.test', 'https://example.test/\\bad', 'http://example.test'] as $url) {
            $this->postJson('/admin/website-links', array_replace($payload, ['url' => $url]))->assertUnprocessable()->assertJsonValidationErrors('url');
        }
        $this->post('/admin/website-links', array_replace($payload, ['placement' => 'footer_legal', 'url' => '/privacy']))->assertRedirect()->assertSessionHasNoErrors();
        $link = WebsiteLink::where('placement', 'social')->sole();
        $this->put('/admin/website-links/'.$link->id, array_replace($payload, ['is_published' => 0]))->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/')->assertOk()->assertDontSee('https://youtube.com/@clinic');
    }

    public function test_footer_settings_validate_and_persist_independently_in_existing_theme(): void
    {
        $this->seed();
        $this->actingAs($this->booker('09120000112', 'admin'));
        $payload = ThemeService::DEFAULTS + ['footer_features' => [['title' => 'ویژگی مستقل', 'text' => 'توضیح مستقل']], 'footer_slogan' => 'شعار مستقل', 'contact_secondary_phone' => '02112345678'];
        $this->put('/admin/website-theme', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/about')->assertOk()->assertSee('ویژگی مستقل')->assertSee('شعار مستقل');
        $this->put(route('website.theme.preview'), ThemeService::DEFAULTS + ['footer_text' => 'PRIVATE-FOOTER-PREVIEW'])->assertOk()->assertSee('PRIVATE-FOOTER-PREVIEW');
        $this->get('/about')->assertOk()->assertDontSee('PRIVATE-FOOTER-PREVIEW');
        $this->putJson('/admin/website-theme', array_replace($payload, ['footer_features' => array_fill(0, 4, ['title' => 'عنوان', 'text' => 'متن'])]))->assertUnprocessable()->assertJsonValidationErrors('footer_features');
        $this->putJson('/admin/website-theme', array_replace($payload, ['contact_secondary_phone' => 'bad']))->assertUnprocessable()->assertJsonValidationErrors('contact_secondary_phone');
        $this->put('/admin/website-theme', ThemeService::DEFAULTS + ['footer_features_present' => '1'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([], app(ThemeService::class)->settings()['footer_features']);
    }

    public function test_unsafe_legacy_links_and_xss_do_not_become_executable_footer_content(): void
    {
        $this->link('social', 'LEGACY-UNSAFE', 'javascript:alert(1)');
        $this->link('footer', 'LEGACY-UNSAFE-MENU', 'javascript:alert(1)');
        $this->link('footer_legal', 'LEGACY-UNSAFE-LEGAL', '//evil.example');
        $this->link('footer', 'WEEKLY-OFF', '/weekly-schedule');
        app(ThemeService::class)->save(['footer_text' => '<script>FOOTER-XSS</script>', 'footer_slogan' => '<img src=x onerror=alert(1)>']);
        $this->get('/')->assertOk()->assertDontSee('LEGACY-UNSAFE')->assertDontSee('WEEKLY-OFF')->assertSee('&lt;script&gt;FOOTER-XSS&lt;/script&gt;', false)->assertDontSee('<script>FOOTER-XSS</script>', false)->assertDontSee('<img src=x onerror=alert(1)>', false);
        $this->get('/admin/website-theme')->assertRedirect('/login');
    }
}
