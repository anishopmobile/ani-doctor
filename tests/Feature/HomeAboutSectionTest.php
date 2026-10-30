<?php

namespace Tests\Feature;

use App\Modules\Website\Application\PageService;
use App\Modules\Website\Application\ThemeService;
use App\Modules\Website\Infrastructure\WebsiteMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

final class HomeAboutSectionTest extends TestCase
{
    use RefreshDatabase;
    use BookingFixtures;

    public function test_each_selected_layout_uses_stored_content_and_only_one_section(): void
    {
        foreach (['classic', 'dark', 'organic'] as $layout) {
            app(ThemeService::class)->save(['home_about_layout' => $layout, 'about_title' => 'عنوان ذخیره‌شده', 'about_text' => 'متن واقعی درباره پزشک', 'home_about_features' => [['title' => 'ویژگی واقعی', 'text' => 'توضیح تأییدشده']], 'home_about_stats' => [['title' => 'مقدار واقعی', 'text' => 'توضیح مقدار']], 'home_about_badge_title' => 'عنوان کارت', 'home_about_button_label' => 'معرفی پزشک', 'home_about_button_url' => '/about']);
            $response = $this->get('/')->assertOk()->assertSee('home-about--'.$layout)->assertSee('عنوان ذخیره‌شده')->assertSee('متن واقعی درباره پزشک')->assertSee('معرفی پزشک')->assertSee('home-about.css')->assertDontSee('images.unsplash.com')->assertDontSee('۵۰۰۰+');
            $this->assertSame(1, substr_count($response->getContent(), 'id="about"'));
            $layout === 'dark' ? $response->assertSee('مقدار واقعی') : $response->assertSee('ویژگی واقعی');
        }
        foreach (['/about', '/services', '/articles', '/privacy', '/calendar'] as $path) {
            $this->get($path)->assertDontSee('assets/home-about.css');
        }
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_layout_and_fields_are_validated_and_private_preview_does_not_publish(): void
    {
        $this->seed();
        $this->actingAs($this->booker('09120000221', 'admin'));
        $this->put('/admin/website-theme', ThemeService::DEFAULTS + ['home_about_layout' => 'organic'])->assertRedirect()->assertSessionHasNoErrors();
        $this->put(route('website.theme.preview'), ThemeService::DEFAULTS + ['home_about_layout' => 'dark', 'about_title' => 'PRIVATE-ABOUT-PREVIEW'])->assertOk()->assertSee('home-about--dark')->assertSee('PRIVATE-ABOUT-PREVIEW');
        $this->get('/')->assertOk()->assertSee('home-about--organic')->assertDontSee('PRIVATE-ABOUT-PREVIEW');
        foreach (['home_about_layout' => 'invalid', 'home_about_button_url' => 'https://external.example', 'home_about_features' => array_fill(0, 5, ['title' => 'عنوان', 'text' => 'متن']), 'home_about_stats' => array_fill(0, 4, ['title' => 'عدد', 'text' => 'توضیح'])] as $field => $bad) {
            $this->putJson('/admin/website-theme', ThemeService::DEFAULTS + [$field => $bad])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->put('/admin/website-theme', ThemeService::DEFAULTS + ['home_about_features_present' => '1', 'home_about_stats_present' => '1'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([], app(ThemeService::class)->settings()['home_about_features']);
        $this->assertSame([], app(ThemeService::class)->settings()['home_about_stats']);
    }

    public function test_about_upload_publication_replacement_and_delete_are_independent_of_portrait(): void
    {
        Storage::fake('local');
        $this->seed();
        $admin = $this->booker('09120000222', 'admin');
        app(ThemeService::class)->save(ThemeService::DEFAULTS);
        $this->actingAs($admin)->post(route('website.media.store', ['portrait', 1]), ['image' => UploadedFile::fake()->image('portrait.png'), 'alt_text' => 'تصویر اصلی پزشک'])->assertRedirect();
        $this->actingAs($admin)->post(route('website.media.store', ['about', 1]), ['image' => UploadedFile::fake()->image('about.jpg'), 'alt_text' => 'تصویر مستقل درباره ما'])->assertRedirect()->assertSessionHasNoErrors();
        $media = WebsiteMedia::where('owner_type', 'about')->sole();
        $this->put('/admin/website-theme', ThemeService::DEFAULTS + ['portrait_is_published' => 1])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get(route('website.media.show', $media))->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('تصویر مستقل درباره ما');
        $this->actingAs($admin)->put('/admin/website-theme', ThemeService::DEFAULTS + ['about_is_published' => 1])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get('/')->assertOk()->assertSee('تصویر مستقل درباره ما')->assertSee('تصویر اصلی پزشک');
        $this->get(route('website.media.show', $media))->assertOk();
        $this->actingAs($admin)->deleteJson(route('website.media.destroy', $media))->assertUnprocessable();
        $oldPath = $media->path;
        $this->post(route('website.media.store', ['about', 1]), ['image' => UploadedFile::fake()->image('replacement.webp'), 'alt_text' => 'تصویر تازه درباره'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse(app(ThemeService::class)->settings()['about_is_published']);
        $this->assertTrue(app(ThemeService::class)->settings()['portrait_is_published']);
        Storage::disk('local')->assertMissing($oldPath);
        $this->delete(route('website.media.destroy', $media->fresh()))->assertRedirect();
        $this->assertDatabaseMissing('website_media', ['owner_type' => 'about']);
        $this->assertDatabaseHas('website_media', ['owner_type' => 'portrait']);
    }

    public function test_upload_requires_permission_safe_owner_id_and_valid_raster_image(): void
    {
        $this->seed();
        foreach (['patient', 'secretary'] as $index => $role) {
            $this->actingAs($this->booker('0912000022'.($index + 3), $role))->postJson(route('website.media.store', ['about', 1]), [])->assertForbidden();
        }
        $this->actingAs($this->booker('09120000225', 'admin'));
        app(ThemeService::class)->save(ThemeService::DEFAULTS);
        $payload = ['image' => UploadedFile::fake()->image('test.png'), 'alt_text' => 'توضیح'];
        $this->post(route('website.media.store', ['about', 2]), $payload)->assertNotFound();
        $this->postJson(route('website.media.store', ['about', 1]), ['image' => UploadedFile::fake()->createWithContent('bad.svg', '<svg onload="alert(1)"/>'), 'alt_text' => 'توضیح'])->assertUnprocessable();
        $this->assertDatabaseCount('website_media', 0);
    }

    public function test_missing_image_empty_metrics_visibility_and_xss_remain_safe(): void
    {
        Storage::fake('local');
        $media = WebsiteMedia::create(['owner_type' => 'about', 'owner_id' => 1, 'path' => 'website-media/missing.jpg', 'mime_type' => 'image/jpeg', 'alt_text' => 'گم‌شده']);
        app(ThemeService::class)->save(['about_is_published' => true, 'home_about_layout' => 'dark', 'about_text' => '<script>ABOUT-XSS</script>', 'home_about_badge_title' => '', 'home_about_badge_text' => '']);
        $this->get('/')->assertOk()->assertSee('assets/clinic-art.webp')->assertDontSee('src="'.route('website.media.show', $media).'"', false)->assertDontSee('class="ha-stats"', false)->assertDontSee('class="ha-badge"', false)->assertDontSee('<script>ABOUT-XSS</script>', false)->assertSee('&lt;script&gt;ABOUT-XSS&lt;/script&gt;', false);
        app(ThemeService::class)->save(['show_about' => false]);
        $this->get('/')->assertOk()->assertDontSee('id="about"', false)->assertDontSee('assets/home-about.css');
    }

    public function test_published_page_builder_home_keeps_its_existing_precedence(): void
    {
        $this->seed();
        $admin = $this->booker('09120000226', 'admin');
        $pages = app(PageService::class);
        $page = $pages->create(['slug' => 'home', 'title' => 'خانه منتشرشده صفحه‌ساز'], $admin->id);
        $pages->publication($page, $page->version, $admin->id, true);
        app(ThemeService::class)->save(['home_about_layout' => 'organic']);
        $this->get('/')->assertOk()->assertSee('خانه منتشرشده صفحه‌ساز')->assertDontSee('assets/home-about.css');
    }
}
