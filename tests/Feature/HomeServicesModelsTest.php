<?php

namespace Tests\Feature;

use App\Modules\Catalog\Infrastructure\Service;
use App\Modules\Website\Application\PageService;
use App\Modules\Website\Application\ServiceContentService;
use App\Modules\Website\Application\ThemeService;
use App\Modules\Website\Infrastructure\WebsiteMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

final class HomeServicesModelsTest extends TestCase
{
    use RefreshDatabase;
    use BookingFixtures;

    private function service(string $name, array $extra = []): Service
    {
        return Service::create(array_replace(['name' => $name, 'price_irr' => 1200000, 'duration_minutes' => 30, 'buffer_minutes' => 0, 'capacity' => 1, 'is_active' => true, 'is_online' => true], $extra));
    }

    public function test_all_layouts_use_real_ordered_online_services_and_published_content_only(): void
    {
        $first = $this->service('خدمت اول واقعی');
        $second = $this->service('خدمت دوم واقعی');
        $this->service('خدمت پنهان غیرفعال', ['is_active' => false]);
        $this->service('خدمت پنهان غیراینترنتی', ['is_online' => false]);
        app(ServiceContentService::class)->save($first->id, ['summary' => 'خلاصه منتشرشده واقعی', 'is_published' => true, 'sort_order' => 10]);
        app(ServiceContentService::class)->save($second->id, ['summary' => 'PRIVATE-SERVICE-DRAFT', 'is_published' => false, 'sort_order' => 20]);
        $before = $first->fresh()->toJson();
        foreach (['light', 'dark', 'pastel'] as $layout) {
            app(ThemeService::class)->save(['home_services_layout' => $layout, 'home_services_badge' => 'نشان ذخیره‌شده', 'home_services_highlights' => [['title' => 'نکته ذخیره‌شده', 'text' => 'توضیح تأییدشده']]]);
            $response = $this->get('/')->assertOk()->assertSee('home-services--'.$layout)->assertSee('خلاصه منتشرشده واقعی')->assertDontSee('PRIVATE-SERVICE-DRAFT')->assertDontSee('خدمت پنهان غیرفعال')->assertDontSee('خدمت پنهان غیراینترنتی')->assertSee(route('website.services.show', $first));
            $this->assertSame(1, substr_count($response->getContent(), 'id="services"'));
            $html = substr($response->getContent(), strpos($response->getContent(), 'id="services"'));
            $this->assertLessThan(strpos($html, 'خدمت اول واقعی'), strpos($html, 'خدمت دوم واقعی'));
        }
        $this->assertSame($before, $first->fresh()->toJson());
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_admin_selects_layout_preview_is_private_and_invalid_configuration_is_rejected(): void
    {
        $this->seed();
        $this->actingAs($this->booker('09120000301', 'admin'));
        app(ThemeService::class)->save(['home_about_layout' => 'organic']);
        $this->put('/admin/website-theme', ThemeService::DEFAULTS + ['home_services_layout' => 'pastel'])->assertRedirect()->assertSessionHasNoErrors();
        $this->put(route('website.theme.preview'), ThemeService::DEFAULTS + ['home_services_layout' => 'dark', 'services_title' => 'PRIVATE-SERVICES-PREVIEW'])->assertOk()->assertSee('home-services--dark')->assertSee('PRIVATE-SERVICES-PREVIEW');
        $this->get('/')->assertOk()->assertSee('home-services--pastel')->assertSee('home-about--organic')->assertDontSee('PRIVATE-SERVICES-PREVIEW');
        $this->putJson('/admin/website-theme', ThemeService::DEFAULTS + ['home_services_layout' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('home_services_layout');
        $this->putJson('/admin/website-theme', ThemeService::DEFAULTS + ['home_services_highlights' => array_fill(0, 5, ['title' => 'عنوان', 'text' => 'متن'])])->assertUnprocessable()->assertJsonValidationErrors('home_services_highlights');
        $this->put('/admin/website-theme', ThemeService::DEFAULTS + ['home_services_highlights_present' => '1'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([], app(ThemeService::class)->settings()['home_services_highlights']);
    }

    public function test_section_image_publication_is_independent_of_about_portrait_and_catalog(): void
    {
        Storage::fake('local');
        $this->seed();
        $admin = $this->booker('09120000302', 'admin');
        $service = $this->service('خدمت واقعی محفوظ');
        $before = $service->fresh()->toJson();
        app(ThemeService::class)->save(ThemeService::DEFAULTS);
        foreach (['portrait', 'about', 'services'] as $type) {
            $this->actingAs($admin)->post(route('website.media.store', [$type, 1]), ['image' => UploadedFile::fake()->image($type.'.png'), 'alt_text' => 'تصویر مستقل '.$type])->assertRedirect()->assertSessionHasNoErrors();
        }
        $media = WebsiteMedia::where('owner_type', 'services')->sole();
        $this->put('/admin/website-theme', ThemeService::DEFAULTS + ['portrait_is_published' => true, 'about_is_published' => true])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get(route('website.media.show', $media))->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('تصویر مستقل services');
        $this->actingAs($admin)->put('/admin/website-theme', ThemeService::DEFAULTS + ['services_is_published' => true])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get('/')->assertOk()->assertSee('تصویر مستقل services')->assertSee('تصویر مستقل about')->assertSee('تصویر مستقل portrait');
        $this->get(route('website.media.show', $media))->assertOk();
        $this->actingAs($admin)->deleteJson(route('website.media.destroy', $media))->assertUnprocessable();
        $oldPath = $media->path;
        $this->post(route('website.media.store', ['services', 1]), ['image' => UploadedFile::fake()->image('replacement.jpg'), 'alt_text' => 'تصویر جایگزین'])->assertRedirect()->assertSessionHasNoErrors();
        $settings = app(ThemeService::class)->settings();
        $this->assertFalse($settings['services_is_published']);
        $this->assertTrue($settings['about_is_published']);
        $this->assertTrue($settings['portrait_is_published']);
        Storage::disk('local')->assertMissing($oldPath);
        $this->delete(route('website.media.destroy', $media->fresh()))->assertRedirect();
        $this->assertSame($before, $service->fresh()->toJson());
    }

    public function test_new_media_slot_preserves_permissions_owner_guard_and_raster_validation(): void
    {
        $this->seed();
        foreach (['patient', 'secretary'] as $index => $role) {
            $this->actingAs($this->booker('0912000030'.($index + 3), $role))->postJson(route('website.media.store', ['services', 1]), [])->assertForbidden();
        }
        $this->actingAs($this->booker('09120000305', 'admin'));
        app(ThemeService::class)->save(ThemeService::DEFAULTS);
        $this->post(route('website.media.store', ['services', 2]), ['image' => UploadedFile::fake()->image('test.png'), 'alt_text' => 'توضیح'])->assertNotFound();
        $this->postJson(route('website.media.store', ['services', 1]), ['image' => UploadedFile::fake()->createWithContent('bad.svg', '<svg onload="alert(1)"/>'), 'alt_text' => 'توضیح'])->assertUnprocessable();
        $this->assertDatabaseCount('website_media', 0);
    }

    public function test_card_and_section_image_fallback_empty_state_and_escaped_content(): void
    {
        Storage::fake('local');
        $service = $this->service('خدمت با تصویر');
        app(ServiceContentService::class)->save($service->id, ['summary' => '<script>SERVICE-XSS</script>', 'is_published' => true]);
        $media = WebsiteMedia::create(['owner_type' => 'service', 'owner_id' => $service->id, 'path' => 'website-media/card.png', 'mime_type' => 'image/png', 'alt_text' => 'تصویر کارت']);
        Storage::disk('local')->put($media->path, 'synthetic bytes');
        $this->get('/')->assertOk()->assertSee('data-home-service-card-image')->assertSee('&lt;script&gt;SERVICE-XSS&lt;/script&gt;', false)->assertDontSee('<script>SERVICE-XSS</script>', false);
        Storage::disk('local')->delete($media->path);
        $this->get('/')->assertOk()->assertDontSee('data-home-service-card-image')->assertSee('assets/clinic-art.webp');
        $service->update(['is_online' => false]);
        $this->get('/')->assertOk()->assertSee('در حال حاضر خدمتی برای رزرو اینترنتی منتشر نشده است.');
        app(ThemeService::class)->save(['show_services' => false]);
        $this->get('/')->assertOk()->assertDontSee('id="services"', false)->assertDontSee('assets/home-services.css');
        foreach (['/services', '/about', '/articles', '/calendar'] as $path) {
            $this->get($path)->assertDontSee('assets/home-services.css');
        }
    }

    public function test_published_page_builder_home_keeps_its_precedence(): void
    {
        $this->seed();
        $admin = $this->booker('09120000306', 'admin');
        $pages = app(PageService::class);
        $page = $pages->create(['slug' => 'home', 'title' => 'خانه منتشرشده مستقل'], $admin->id);
        $pages->publication($page, $page->version, $admin->id, true);
        app(ThemeService::class)->save(['home_services_layout' => 'dark']);
        $this->get('/')->assertOk()->assertSee('خانه منتشرشده مستقل')->assertDontSee('assets/home-services.css');
    }
}
