<?php

namespace Tests\Feature;

use App\Modules\Website\Application\ThemeService;
use App\Modules\Website\Application\PageService;
use App\Modules\Website\Infrastructure\WebsiteContactMessage;
use App\Modules\Website\Infrastructure\WebsiteLink;
use App\Modules\Website\Infrastructure\WebsiteMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

final class HomeContactModelsTest extends TestCase
{
    use RefreshDatabase;
    use BookingFixtures;

    private function payload(): array
    {
        return ['contact_sender' => 'نام ساختگی پیام', 'contact_mobile' => '۰۹۱۲۰۰۰۰۰۰۰', 'contact_message' => '<script>CONTACT-XSS</script> متن ساختگی', 'contact_submission' => (string) Str::uuid()];
    }

    public function test_all_models_use_saved_contact_data_and_only_published_whatsapp(): void
    {
        WebsiteLink::create(['placement' => 'social', 'label' => 'واتساپ ساختگی', 'url' => 'https://wa.me/989120000000', 'is_published' => true]);
        WebsiteLink::create(['placement' => 'social', 'label' => 'پنهان', 'url' => 'https://wa.me/989130000000', 'is_published' => false]);
        foreach (['clinic', 'dark', 'pastel'] as $layout) {
            app(ThemeService::class)->save(['home_contact_layout' => $layout, 'contact_address' => 'آدرس ذخیره‌شده آزمون', 'contact_hours' => 'ساعات واقعی ذخیره‌شده', 'contact_email' => 'public@example.test', 'contact_secondary_phone' => '02112345678', 'contact_map_url' => 'https://maps.google.com/?q=test', 'home_contact_intro' => '<script>INTRO-XSS</script>']);
            $response = $this->get('/')->assertOk()->assertSee('home-contact--'.$layout)->assertSee('آدرس ذخیره‌شده آزمون')->assertSee('ساعات واقعی ذخیره‌شده')->assertSee('mailto:public@example.test')->assertSee('tel:02112345678')->assertSee('https://wa.me/989120000000')->assertDontSee('https://wa.me/989130000000')->assertSee('&lt;script&gt;INTRO-XSS&lt;/script&gt;', false)->assertDontSee('<script>INTRO-XSS</script>', false)->assertSee('https://maps.google.com/?q=test');
            $this->assertSame(1, substr_count($response->getContent(), 'id="contact"'));
            $this->assertSame($layout === 'dark' ? 0 : 1, substr_count($response->getContent(), 'class="hc-form"'));
        }
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_admin_selects_valid_model_and_preview_does_not_publish_or_accept_messages(): void
    {
        $this->seed();
        $this->actingAs($this->booker('09120000401', 'admin'));
        $this->put('/admin/website-theme', ThemeService::DEFAULTS + ['home_contact_layout' => 'pastel'])->assertSessionHasNoErrors();
        $this->put(route('website.theme.preview'), ThemeService::DEFAULTS + ['home_contact_layout' => 'clinic', 'contact_title' => 'PRIVATE-CONTACT-PREVIEW'])->assertOk()->assertSee('home-contact--clinic')->assertSee('PRIVATE-CONTACT-PREVIEW')->assertSee('<fieldset disabled', false);
        $this->get('/')->assertSee('home-contact--pastel')->assertDontSee('PRIVATE-CONTACT-PREVIEW');
        $this->putJson('/admin/website-theme', ThemeService::DEFAULTS + ['home_contact_layout' => '<script>'])->assertUnprocessable()->assertJsonValidationErrors('home_contact_layout');
        $this->putJson('/admin/website-theme', ThemeService::DEFAULTS + ['contact_map_url' => 'javascript:alert(1)'])->assertUnprocessable();
        app(ThemeService::class)->save(['home_contact_form_enabled' => false]);
        $this->get('/')->assertDontSee('class="hc-form"', false);
        $this->post(route('website.contact-messages.store'), $this->payload())->assertForbidden();
    }

    public function test_guest_message_is_encrypted_idempotent_and_private_until_admin_reads_and_deletes(): void
    {
        $data = $this->payload();
        $this->post(route('website.contact-messages.store'), $data)->assertRedirect(route('website.home').'#contact')->assertSessionHas('contact_status');
        $this->post(route('website.contact-messages.store'), $data)->assertRedirect();
        $this->assertDatabaseCount('website_contact_messages', 1);
        $raw = DB::table('website_contact_messages')->first();
        foreach (['sender_name', 'phone', 'message'] as $key) {
            $this->assertStringNotContainsString('CONTACT-XSS', $raw->$key);
            $this->assertStringNotContainsString('09120000000', $raw->$key);
        }
        $message = WebsiteContactMessage::sole();
        $this->assertSame($data['contact_message'], $message->message);
        $this->assertSame('09120000000', $message->phone);
        $this->get('/')->assertDontSee('CONTACT-XSS');
        $this->get(route('website.contact-messages.index'))->assertRedirect(route('login'));
        $this->seed();
        foreach (['patient', 'secretary'] as $i => $role) {
            $this->actingAs($this->booker('0912000040'.($i + 2), $role));
            $this->get(route('website.contact-messages.index'))->assertForbidden();
            $this->put(route('website.contact-messages.update', $message))->assertForbidden();
            $this->delete(route('website.contact-messages.destroy', $message))->assertForbidden();
        }
        $this->actingAs($this->booker('09120000404', 'admin'));
        $this->get(route('website.contact-messages.index'))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee('&lt;script&gt;CONTACT-XSS&lt;/script&gt;', false)->assertDontSee('<script>CONTACT-XSS</script>', false);
        $this->put(route('website.contact-messages.update', $message))->assertRedirect();
        $this->assertNotNull($message->fresh()->read_at);
        $this->delete(route('website.contact-messages.destroy', $message))->assertRedirect();
        $this->assertDatabaseCount('website_contact_messages', 0);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_validation_honeypot_rate_limit_and_csrf_are_enforced(): void
    {
        $this->postJson(route('website.contact-messages.store'), [])->assertUnprocessable()->assertJsonValidationErrors(['contact_sender', 'contact_mobile', 'contact_message', 'contact_submission']);
        $this->postJson(route('website.contact-messages.store'), array_replace($this->payload(), ['contact_company' => 'bot', 'contact_mobile' => 'bad', 'contact_message' => str_repeat('a', 2001)]))->assertUnprocessable()->assertJsonValidationErrors(['contact_company', 'contact_mobile', 'contact_message']);
        $this->post(route('website.contact-messages.store'), array_replace($this->payload(), ['contact_sender' => '', 'contact_mobile' => 'bad']))->assertRedirect(route('website.home').'#contact');
        // Inspect without restarting the test session, which would age its flash before the redirected GET.
        $this->assertArrayHasKey('contact_sender', app('session.store')->get('errors')['contact']['messages']);
        $this->assertArrayNotHasKey('contact_mobile', app('session.store')->get('_old_input', []));
        $this->assertArrayNotHasKey('contact_message', app('session.store')->get('_old_input', []));
        $this->get(route('website.home'))->assertOk()->assertSee('پیام ارسال نشد؛ ورودی‌ها را بررسی کنید.')->assertSee('aria-invalid="true"', false);
        $this->postJson(route('website.contact-messages.store'), $this->payload())->assertStatus(429);
        $this->assertDatabaseCount('website_contact_messages', 0);
        $this->app->instance('env', 'local');
        $this->post(route('website.contact-messages.store'), $this->payload())->assertStatus(419);
    }

    public function test_contact_image_publication_is_independent_and_invalid_uploads_are_rejected(): void
    {
        Storage::fake('local');
        $this->seed();
        $admin = $this->booker('09120000405', 'admin');
        $this->actingAs($admin);
        app(ThemeService::class)->save(ThemeService::DEFAULTS + ['about_is_published' => true, 'services_is_published' => true]);
        $this->post(route('website.media.store', ['contact', 2]), ['image' => UploadedFile::fake()->image('contact.png'), 'alt_text' => 'تصویر'])->assertNotFound();
        $this->postJson(route('website.media.store', ['contact', 1]), ['image' => UploadedFile::fake()->createWithContent('bad.svg', '<svg/>'), 'alt_text' => 'تصویر'])->assertUnprocessable();
        $this->post(route('website.media.store', ['contact', 1]), ['image' => UploadedFile::fake()->image('contact.png'), 'alt_text' => 'تصویر مستقل تماس'])->assertRedirect();
        $media = WebsiteMedia::where('owner_type', 'contact')->sole();
        auth()->logout();
        $this->get(route('website.media.show', $media))->assertNotFound();
        $this->get('/')->assertSee('clinic-art.webp')->assertDontSee('تصویر مستقل تماس');
        $this->actingAs($admin)->put('/admin/website-theme', ThemeService::DEFAULTS + ['contact_is_published' => true])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get('/')->assertSee('تصویر مستقل تماس');
        $this->get(route('website.media.show', $media))->assertOk();
        $this->actingAs($admin)->deleteJson(route('website.media.destroy', $media))->assertUnprocessable();
        $this->post(route('website.media.store', ['contact', 1]), ['image' => UploadedFile::fake()->image('replacement.jpg'), 'alt_text' => 'جایگزین'])->assertRedirect();
        $theme = app(ThemeService::class)->settings();
        $this->assertFalse($theme['contact_is_published']);
        $this->assertTrue($theme['about_is_published']);
        $this->assertTrue($theme['services_is_published']);
        Storage::disk('local')->assertMissing($media->path);
        $this->delete(route('website.media.destroy', $media->fresh()))->assertRedirect();
    }

    public function test_optional_fields_visibility_page_builder_and_other_pages_are_preserved(): void
    {
        app(ThemeService::class)->save(['contact_email' => '', 'contact_map_url' => '', 'contact_hours' => '', 'home_contact_intro' => '', 'home_contact_form_enabled' => false]);
        $this->get('/')->assertOk()->assertDontSee('mailto:')->assertDontSee('hc-intro')->assertDontSee('class="hc-form"', false);
        foreach (['/contact', '/about', '/services', '/articles', '/calendar'] as $path) {
            $this->get($path)->assertDontSee('assets/home-contact.css');
        }
        app(ThemeService::class)->save(['show_contact' => false]);
        $this->get('/')->assertDontSee('id="contact"', false)->assertDontSee('assets/home-contact.css');
        $this->post(route('website.contact-messages.store'), $this->payload())->assertForbidden();
        $this->seed();
        $admin = $this->booker('09120000406', 'admin');
        $pages = app(PageService::class);
        $page = $pages->create(['slug' => 'home', 'title' => 'خانه صفحه‌ساز'], $admin->id);
        $pages->publication($page, $page->version, $admin->id, true);
        app(ThemeService::class)->save(['show_contact' => true]);
        $this->get('/')->assertSee('خانه صفحه‌ساز')->assertDontSee('assets/home-contact.css');
    }
}
