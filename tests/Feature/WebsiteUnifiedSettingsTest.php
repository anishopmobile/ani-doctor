<?php

namespace Tests\Feature;

use App\Modules\Catalog\Infrastructure\Service;
use App\Modules\Identity\Infrastructure\Role;
use App\Modules\Identity\Infrastructure\User;
use App\Modules\Website\Application\ThemeService;
use App\Modules\Website\Infrastructure\WebsiteMedia;
use App\Modules\Website\Infrastructure\WebsiteTheme;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class WebsiteUnifiedSettingsTest extends TestCase
{
    use DatabaseMigrations;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->admin = $this->actor('admin', '09000000601');
        $this->actingAs($this->admin);
    }

    private function actor(string $role, string $mobile): User
    {
        $user = User::create(['mobile' => $mobile]);
        $user->forceFill(['mobile_verified_at' => now()])->save();
        $user->roles()->attach(Role::where('name', $role)->sole());
        return $user;
    }

    private function save(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->putJson(route('website.theme.update'), array_replace(ThemeService::DEFAULTS, $extra));
    }

    public function test_copy_contact_order_visibility_and_safe_faq_are_read_from_one_source(): void
    {
        $this->save(['hero_title' => '<script>custom</script>', 'about_text' => 'متن تأییدشده مطب', 'contact_email' => 'clinic@example.test',
            'contact_hours' => 'ساعت پاسخ‌گویی ساختگی آزمون', 'contact_map_url' => 'https://maps.example.test?q=clinic', 'section_order' => ['contact', 'faq', 'services', 'why', 'appointment', 'about'],
            'show_why' => 0, 'show_faq' => 1, 'faq_items' => [['title' => 'پرسش آزمون', 'text' => 'پاسخ آزمون']], 'footer_text' => 'فوتر مشترک'])
            ->assertRedirect()->assertSessionHasNoErrors();
        auth()->logout();
        $response = $this->get('/')->assertOk()->assertSee('&lt;script&gt;custom&lt;/script&gt;', false)->assertDontSee('<script>custom</script>', false)
            ->assertSee('متن تأییدشده مطب')->assertSee('پرسش آزمون')->assertSee('mailto:clinic@example.test', false)->assertSee('فوتر مشترک')->assertDontSee('class="why-grid"', false);
        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'id="about"'), strpos($html, 'id="contact"'));
        $this->get('/services')->assertOk()->assertSee('فوتر مشترک');
        $this->get('/about')->assertOk()->assertSee('متن تأییدشده مطب');
        $this->get('/contact')->assertOk()->assertSee('mailto:clinic@example.test', false);
        $this->get('/login')->assertOk()->assertSee('assets/design-system.css')->assertSee('website-theme.css')->assertDontSee('assets/studio.css');
    }

    public function test_invalid_urls_colors_orders_and_nested_shapes_cannot_be_saved(): void
    {
        foreach ([['hero_button_url' => '//evil.example'], ['contact_map_url' => 'javascript:alert(1)'], ['contact_website' => 'https://user:pass@evil.example'],
            ['contact_email' => 'bad<script>'], ['secondary_color' => '#123456;url(x)'], ['background_color' => '#111111'],
            ['section_order' => array_fill(0, 6, 'about')],
            ['primary_color' => '#000000', 'text_color' => '#000000', 'primary_hover_color' => '#000000', 'secondary_color' => '#000000', 'accent_color' => '#000000', 'surface_color' => '#888888', 'background_color' => '#888888'], ['faq_items' => [['title' => 'x', 'text' => 'y', 'html' => '<script>']]]] as $extra) {
            $this->save($extra)->assertUnprocessable();
        }
        $this->assertDatabaseCount('website_theme_settings', 0);
    }

    public function test_stale_settings_are_rejected_and_preview_never_changes_public_data(): void
    {
        $this->save(['hero_title' => 'نسخه اول'])->assertSessionHasNoErrors();
        $version = app(ThemeService::class)->settings()['settings_version'];
        $this->save(['expected_settings_version' => $version, 'hero_title' => 'نسخه دوم'])->assertRedirect();
        $this->save(['expected_settings_version' => $version, 'hero_title' => 'بازنویسی اشتباه'])->assertUnprocessable()->assertJsonValidationErrors('expected_settings_version');
        $this->put(route('website.theme.preview'), array_replace(ThemeService::DEFAULTS, ['hero_title' => 'پیش‌نویس خصوصی', 'primary_color' => '#112233', 'show_about' => '0']))
            ->assertOk()->assertSee('پیش‌نویس خصوصی')->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('website.theme.preview-css'))->assertOk()->assertHeader('Vary', 'Cookie')->assertSee('--brand-primary: #112233;', false);
        $this->get('/website-theme.css')->assertSee('--brand-primary: #087b80;', false);
        $this->assertFalse(session('website_theme_preview.show_about'));
        $this->get('/')->assertSee('نسخه دوم')->assertDontSee('پیش‌نویس خصوصی');
        $this->assertSame('نسخه دوم', app(ThemeService::class)->settings()['hero_title']);
        $this->flushSession();
        $this->get(route('website.theme.preview-css'))->assertNotFound();
        auth()->logout();
        $this->getJson(route('website.theme.preview-css'))->assertUnauthorized();
    }

    public function test_portrait_and_favicon_are_private_independent_and_require_publication_before_use(): void
    {
        Storage::fake('local');
        $this->save()->assertRedirect();
        foreach (['portrait', 'favicon'] as $type) {
            $this->post(route('website.media.store', [$type, 1]), ['image' => UploadedFile::fake()->image($type.'.png'), 'alt_text' => 'تصویر آزمون '.$type])->assertSessionHasNoErrors();
        }
        $portrait = WebsiteMedia::where('owner_type', 'portrait')->sole();
        $favicon = WebsiteMedia::where('owner_type', 'favicon')->sole();
        auth()->logout();
        $this->get(route('website.media.show', $portrait))->assertNotFound();
        $this->get(route('website.media.show', $favicon))->assertNotFound();
        $this->actingAs($this->admin);
        $this->save(['portrait_is_published' => 1, 'favicon_is_published' => 1])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get('/')->assertSee('تصویر آزمون portrait')->assertSee(route('website.media.show', $favicon), false);
        $this->get(route('website.media.show', $portrait))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->admin)->deleteJson(route('website.media.destroy', $portrait))->assertUnprocessable();
        Storage::disk('local')->assertExists($portrait->path);
        $oldPath = $portrait->path;
        $this->post(route('website.media.store', ['portrait', 1]), ['image' => UploadedFile::fake()->image('replacement.jpg'), 'alt_text' => 'پیش‌نویس تازه'])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($oldPath);
        $this->assertFalse(app(ThemeService::class)->settings()['portrait_is_published']);
        $this->assertTrue(app(ThemeService::class)->settings()['favicon_is_published']);
        $portrait->refresh();
        $this->delete(route('website.media.destroy', $portrait))->assertRedirect();
        Storage::disk('local')->assertMissing($portrait->path);
        $this->post(route('website.media.store', ['portrait', 2]), ['image' => UploadedFile::fake()->image('bad.jpg'), 'alt_text' => 'بد'])->assertNotFound();
        $this->postJson(route('website.media.store', ['favicon', 1]), ['image' => UploadedFile::fake()->createWithContent('bad.svg', '<svg onload="alert(1)"/>'), 'alt_text' => 'بد'])->assertUnprocessable();
        $this->assertDatabaseCount('website_media', 1);
    }

    public function test_patient_and_secretary_cannot_edit_preview_or_upload_identity_media(): void
    {
        foreach (['patient' => '09000000602', 'secretary' => '09000000603'] as $role => $mobile) {
            $this->actingAs($this->actor($role, $mobile));
            $this->get(route('website.theme.edit'))->assertForbidden();
            $this->save()->assertForbidden();
            $this->putJson(route('website.theme.preview'), ThemeService::DEFAULTS)->assertForbidden();
            $this->postJson(route('website.media.store', ['portrait', 1]), [])->assertForbidden();
        }
        $this->assertDatabaseCount('website_theme_settings', 0);
    }

    public function test_old_settings_are_preserved_and_empty_lists_can_be_saved_intentionally(): void
    {
        $theme = WebsiteTheme::create(['id' => 1, 'primary_color' => '#112233', 'text_color' => '#334455', 'surface_color' => '#fafafa', 'brand_settings' => ['brand_name' => 'نام قبلی', 'legacy_note' => 'keep']]);
        app(ThemeService::class)->save(['hero_title' => 'عنوان تازه']);
        $this->assertSame('#112233', $theme->fresh()->primary_color);
        $this->assertSame('keep', $theme->fresh()->brand_settings['legacy_note']);
        $this->assertSame('نام قبلی', app(ThemeService::class)->settings()['brand_name']);
        $this->save(['why_items_present' => '1', 'faq_items_present' => '1'])->assertRedirect();
        $this->assertSame([], app(ThemeService::class)->settings()['why_items']);
        $this->assertSame([], app(ThemeService::class)->settings()['faq_items']);
    }

    public function test_invalid_old_input_still_renders_a_usable_settings_form(): void
    {
        $this->from(route('website.theme.edit'))->put(route('website.theme.update'), ThemeService::DEFAULTS + ['brand_name' => ['bad'], 'section_order' => [['bad']], 'faq_items' => 'bad'])->assertSessionHasErrors();
        $this->get(route('website.theme.edit'))->assertOk()->assertSee('ذخیره تنظیمات سایت');
    }

    public function test_service_display_order_changes_only_public_presentation(): void
    {
        $first = Service::create(['name' => 'الف', 'price_irr' => 100000, 'duration_minutes' => 30, 'buffer_minutes' => 0, 'capacity' => 1, 'is_active' => true, 'is_online' => true]);
        $second = Service::create(['name' => 'ب', 'price_irr' => 200000, 'duration_minutes' => 20, 'buffer_minutes' => 0, 'capacity' => 1, 'is_active' => true, 'is_online' => true]);
        $this->put(route('website.service-content.update', $first), ['is_published' => 1, 'sort_order' => 20])->assertSessionHasNoErrors();
        $this->put(route('website.service-content.update', $second), ['is_published' => 1, 'sort_order' => 1])->assertSessionHasNoErrors();
        $this->assertSame([$second->id, $first->id], app(\App\Modules\Website\Application\ServiceContentService::class)->catalogQuery()->pluck('id')->all());
        $this->assertSame(100000, $first->fresh()->price_irr);
        $this->putJson(route('website.service-content.update', $first), ['is_published' => 1, 'sort_order' => -1])->assertUnprocessable();
        $this->assertSame(20, $first->websiteContent->sort_order);
    }

    public function test_asset_requests_do_not_replace_navigation_history_or_weaken_session_headers(): void
    {
        $this->get(route('website.theme.edit'))->assertOk();
        $this->get('/website-theme.css')->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSessionHas('_previous.url', route('website.theme.edit'));
        $this->put(route('website.theme.preview'), ThemeService::DEFAULTS)->assertOk();
        $this->get(route('website.theme.preview-css'))->assertOk()->assertSessionHas('_previous.url', route('website.theme.edit'));
        $this->save(['hero_title' => 'پس از پیش‌نمایش'])->assertRedirect(route('website.theme.edit'));
        $this->assertTrue(config('session.http_only'));
        $this->assertTrue(config('session.encrypt'));
    }

    public function test_new_preview_and_identity_mutations_still_require_csrf(): void
    {
        $this->app->instance('env', 'local');
        $this->put(route('website.theme.preview'), ThemeService::DEFAULTS)->assertStatus(419);
        $this->post(route('website.media.store', ['portrait', 1]), [])->assertStatus(419);
        $this->assertDatabaseCount('website_theme_settings', 0);
        $this->assertDatabaseCount('website_media', 0);
    }

    public function test_display_order_migration_rolls_back_without_removing_catalog_or_editorial_rows(): void
    {
        $service = Service::create(['name' => 'خدمت مهاجرت', 'price_irr' => 100000, 'duration_minutes' => 30, 'buffer_minutes' => 0, 'capacity' => 1, 'is_active' => true, 'is_online' => true]);
        $this->put(route('website.service-content.update', $service), ['summary' => 'متن حفظ‌شونده', 'is_published' => 1, 'sort_order' => 9])->assertSessionHasNoErrors();
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('weekly_announcements'));
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('website_service_contents', 'sort_order'));
        $this->assertDatabaseHas('website_service_contents', ['service_id' => $service->id, 'summary' => 'متن حفظ‌شونده']);
        $this->assertDatabaseHas('services', ['id' => $service->id, 'price_irr' => 100000]);
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertDatabaseHas('website_service_contents', ['service_id' => $service->id, 'sort_order' => 0, 'summary' => 'متن حفظ‌شونده']);
    }
}
