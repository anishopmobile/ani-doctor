<?php

namespace Tests\Feature;

use App\Modules\Identity\Infrastructure\Role;
use App\Modules\Identity\Infrastructure\User;
use App\Modules\Website\Application\PageService;
use App\Modules\Website\Application\ThemeService;
use App\Modules\Website\Infrastructure\WebsiteMedia;
use App\Modules\Website\Infrastructure\WebsitePage;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class WebsitePageBuilderTest extends TestCase
{
    use DatabaseMigrations;

    private function editor(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::create(['mobile' => '09120000441']);
        $user->forceFill(['mobile_verified_at' => now()])->save();
        $user->roles()->attach(Role::where('name', 'admin')->firstOrFail());
        $this->actingAs($user);

        return $user;
    }

    private function page(string $slug = 'guide'): WebsitePage
    {
        return app(PageService::class)->create(['title' => 'راهنمای اولیه', 'slug' => $slug], auth()->id());
    }

    private function block(array $extra = []): array
    {
        return array_replace(['id' => (string) Str::uuid(), 'type' => 'text', 'heading' => 'عنوان عمومی', 'text' => 'متن عمومی', 'layout' => 'stacked', 'background' => 'plain'], $extra);
    }

    private function save(WebsitePage $page, array $blocks, array $extra = [])
    {
        return $this->putJson(route('website.pages.update', $page), array_replace(['title' => 'عنوان ذخیره‌شده', 'meta_description' => 'توضیح صفحه', 'blocks' => $blocks, 'expected_version' => $page->fresh()->version], $extra));
    }

    public function test_page_lifecycle_preserves_published_snapshot_and_restores_history_to_draft_only(): void
    {
        $this->editor();
        $this->post(route('website.pages.store'), ['title' => 'راهنما', 'slug' => 'guide'])->assertRedirect();
        $page = WebsitePage::firstOrFail();
        $this->get(route('website.pages.edit', $page))->assertOk()->assertSee('page-builder.js');
        $this->save($page, [$this->block()])->assertOk()->assertJsonPath('version', 2);
        $revision = $page->revisions()->where('version', 2)->firstOrFail();
        $this->get(route('website.pages.preview', $page))->assertOk()->assertSee('عنوان عمومی')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')->assertDontSee('rel="canonical"', false);
        auth()->logout();
        $this->get('/pages/guide')->assertNotFound();
        $this->get('/sitemap.xml')->assertDontSee('/pages/guide');
        $this->actingAs(User::where('mobile', '09120000441')->firstOrFail());
        $this->postJson(route('website.pages.publish', $page), ['expected_version' => 2])->assertOk()->assertJsonPath('published_version', 3);
        $this->save($page, [$this->block(['heading' => 'پیش‌نویس محرمانه'])])->assertOk();
        auth()->logout();
        $this->get('/pages/guide')->assertOk()->assertSee('عنوان عمومی')->assertDontSee('پیش‌نویس محرمانه');
        $this->get('/sitemap.xml')->assertSee('/pages/guide');
        $this->get(route('website.pages.preview', $page))->assertRedirect('/login');
        $this->actingAs(User::where('mobile', '09120000441')->firstOrFail());
        $this->postJson(route('website.pages.restore', [$page, $revision]), ['expected_version' => 4])->assertOk()->assertJsonPath('version', 5);
        $this->assertSame('عنوان عمومی', $page->fresh()->blocks[0]['heading']);
        $this->assertSame(3, $page->fresh()->published_version);
        $this->postJson(route('website.pages.unpublish', $page), ['expected_version' => 5])->assertOk();
        $this->get('/pages/guide')->assertNotFound();
        $this->deleteJson(route('website.pages.destroy', $page), ['expected_version' => 6])->assertRedirect();
        $this->assertDatabaseCount('website_pages', 0);
        $this->assertDatabaseCount('website_page_revisions', 0);
    }

    public function test_home_override_is_explicit_and_can_be_reverted_without_removing_legacy_content(): void
    {
        $admin = $this->editor();
        $page = $this->page('home');
        $this->save($page, [$this->block(['heading' => 'صفحه اصلی تازه'])])->assertOk();
        $this->get('/')->assertDontSee('صفحه اصلی تازه');
        app(PageService::class)->publication($page, 2, $admin->id, true);
        $this->get('/')->assertOk()->assertSee('صفحه اصلی تازه');
        $this->get('/pages/home')->assertRedirect('/');
        app(PageService::class)->publication($page, 3, $admin->id, false);
        $this->get('/')->assertOk()->assertDontSee('صفحه اصلی تازه');
    }

    public function test_stale_edits_publication_deletion_and_cross_page_restore_cannot_overwrite_newer_state(): void
    {
        $this->editor();
        $page = $this->page();
        $other = $this->page('other');
        $this->save($page, [$this->block()])->assertOk();
        $this->save($page, [$this->block(['heading' => 'قدیمی'])], ['expected_version' => 1])->assertUnprocessable()->assertJsonValidationErrors('expected_version');
        foreach (['publish', 'unpublish'] as $operation) {
            $this->postJson(route('website.pages.'.$operation, $page), ['expected_version' => 1])->assertUnprocessable();
        }
        $this->deleteJson(route('website.pages.destroy', $page), ['expected_version' => 1])->assertUnprocessable();
        $this->postJson(route('website.pages.restore', [$page, $other->revisions()->firstOrFail()]), ['expected_version' => 2])->assertNotFound();
        $this->assertSame(2, $page->fresh()->version);
    }

    public function test_all_editor_routes_reject_a_verified_patient(): void
    {
        $this->editor();
        $page = $this->page();
        $patient = User::create(['mobile' => '09120000442']);
        $patient->forceFill(['mobile_verified_at' => now()])->save();
        $this->actingAs($patient);
        foreach (['index', 'edit', 'preview'] as $operation) {
            $this->get(route('website.pages.'.$operation, $operation === 'index' ? [] : [$page]))->assertForbidden();
        }
        $this->postJson(route('website.pages.store'), ['title' => 'bad', 'slug' => 'bad'])->assertForbidden();
        $this->save($page, [$this->block()])->assertForbidden();
        foreach (['publish', 'unpublish'] as $operation) {
            $this->postJson(route('website.pages.'.$operation, $page), ['expected_version' => 1])->assertForbidden();
        }
        $this->postJson(route('website.pages.assets.store', $page))->assertForbidden();
        $this->postJson(route('website.pages.restore', [$page, 1]), ['expected_version' => 1])->assertForbidden();
        $this->deleteJson(route('website.pages.assets.destroy', [$page, 1]))->assertForbidden();
        $this->deleteJson(route('website.pages.destroy', $page), ['expected_version' => 1])->assertForbidden();
    }

    public function test_typed_blocks_escape_markup_and_reject_executable_urls_unknown_fields_duplicates_and_oversize_payloads(): void
    {
        $admin = $this->editor();
        $page = $this->page();
        foreach (['javascript:alert(1)', '//evil.test', '/\\evil.test', 'https://evil.test'] as $url) {
            $this->save($page, [$this->block(['button_label' => 'کلیک', 'button_url' => $url])])->assertUnprocessable();
        }
        foreach ([['type' => 'html'], ['html' => '<script>bad</script>'], ['layout' => 'injected'], ['background' => 'red'], ['asset_id' => 9999], ['anchor' => 'main']] as $extra) {
            $this->save($page, [$this->block($extra)])->assertUnprocessable();
        }
        $block = $this->block();
        $this->save($page, [$block, $block])->assertUnprocessable();
        $this->save($page, array_fill(0, 41, $block))->assertUnprocessable();
        $this->save($page, [$this->block(['text' => str_repeat('a', 4001)])])->assertUnprocessable();
        $this->save($page, [$this->block(['heading' => '<script>alert(1)</script>', 'text' => '<img src=x onerror=alert(1)>', 'button_label' => 'تقویم', 'button_url' => '/calendar'])])->assertOk();
        app(PageService::class)->publication($page, 2, $admin->id, true);
        $this->get('/pages/guide')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertSee('href="/calendar"', false);
    }

    public function test_page_media_is_immutable_scoped_private_until_published_and_protected_while_referenced(): void
    {
        Storage::fake('local');
        $admin = $this->editor();
        $page = $this->page();
        $other = $this->page('other');
        $this->post(route('website.pages.assets.store', $page), ['image' => UploadedFile::fake()->image('cover.jpg'), 'alt_text' => 'عکس صفحه'])->assertSessionHasNoErrors();
        $asset = $page->assets()->with('media')->firstOrFail();
        $media = $asset->media;
        $this->post(route('website.media.store', ['page_asset', $asset]))->assertNotFound();
        $this->save($other, [$this->block(['type' => 'image', 'asset_id' => $asset->id])])->assertUnprocessable();
        $this->delete(route('website.pages.assets.destroy', [$other, $asset]))->assertNotFound();
        auth()->logout();
        $this->get(route('website.media.show', $media))->assertNotFound();
        $this->actingAs($admin);
        $this->save($page, [$this->block(['type' => 'image', 'asset_id' => $asset->id])])->assertOk();
        $this->deleteJson(route('website.pages.assets.destroy', [$page, $asset]))->assertUnprocessable();
        app(PageService::class)->publication($page, 2, $admin->id, true);
        $revision = $page->revisions()->where('version', 2)->firstOrFail();
        auth()->logout();
        $this->get(route('website.media.show', $media))->assertOk();
        $this->get('/pages/guide')->assertSee('عکس صفحه');
        $this->actingAs($admin);
        $this->save($page, [$this->block()])->assertOk();
        $this->deleteJson(route('website.media.destroy', $media))->assertUnprocessable();
        app(PageService::class)->publication($page, 4, $admin->id, true);
        $this->delete(route('website.pages.assets.destroy', [$page, $asset]))->assertRedirect();
        Storage::disk('local')->assertMissing($media->path);
        $this->postJson(route('website.pages.restore', [$page, $revision]), ['expected_version' => 5])->assertUnprocessable();
        $this->assertDatabaseCount('website_page_assets', 0);
    }

    public function test_deleting_page_removes_media_files_revisions_and_blocks_public_delivery(): void
    {
        Storage::fake('local');
        $this->editor();
        $page = $this->page();
        $this->post(route('website.pages.assets.store', $page), ['image' => UploadedFile::fake()->image('cover.png'), 'alt_text' => 'تصویر'])->assertSessionHasNoErrors();
        $media = WebsiteMedia::firstOrFail();
        $this->deleteJson(route('website.pages.destroy', $page), ['expected_version' => 1])->assertRedirect();
        Storage::disk('local')->assertMissing($media->path);
        $this->assertDatabaseCount('website_media', 0);
        $this->assertDatabaseCount('website_page_assets', 0);
        $this->assertDatabaseCount('website_page_revisions', 0);
        $this->get(route('website.media.show', $media))->assertNotFound();
    }

    public function test_brand_logo_has_separate_explicit_publication_replacement_and_file_deletion(): void
    {
        Storage::fake('local');
        $admin = $this->editor();
        app(ThemeService::class)->save(ThemeService::DEFAULTS);
        $url = route('website.media.store', ['brand', 1]);
        $this->post($url, ['image' => UploadedFile::fake()->image('logo.png'), 'alt_text' => 'نشان مطب'])->assertSessionHasNoErrors();
        $media = WebsiteMedia::firstOrFail();
        $oldPath = $media->path;
        auth()->logout();
        $this->get(route('website.media.show', $media))->assertNotFound();
        $this->get('/')->assertDontSee('نشان مطب');
        $this->actingAs($admin)->put(route('website.theme.update'), ThemeService::DEFAULTS + ['logo_is_published' => '1'])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get('/')->assertSee('نشان مطب');
        $this->get('/services')->assertSee('نشان مطب');
        $this->get(route('website.media.show', $media))->assertOk();
        $this->actingAs($admin)->post($url, ['image' => UploadedFile::fake()->image('new.jpg'), 'alt_text' => 'نشان تازه'])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($oldPath);
        $this->post(route('website.media.store', ['brand', 999]), ['image' => UploadedFile::fake()->image('bad.jpg'), 'alt_text' => 'بد'])->assertNotFound();
        $media->refresh();
        $this->delete(route('website.media.destroy', $media))->assertRedirect();
        Storage::disk('local')->assertMissing($media->path);
        $this->assertDatabaseCount('website_media', 0);
    }

    public function test_every_supported_block_renders_with_real_catalog_and_article_publication_rules(): void
    {
        $admin = $this->editor();
        $page = $this->page();
        $service = \App\Modules\Catalog\Infrastructure\Service::create(['name' => 'خدمت قابل رزرو', 'price_irr' => 1000, 'duration_minutes' => 30, 'buffer_minutes' => 0, 'capacity' => 1, 'is_active' => true, 'is_online' => true]);
        \App\Modules\Catalog\Infrastructure\Service::create(['name' => 'خدمت آفلاین', 'price_irr' => 1000, 'duration_minutes' => 30, 'buffer_minutes' => 0, 'capacity' => 1, 'is_active' => true, 'is_online' => false]);
        \App\Modules\Website\Infrastructure\WebsiteArticle::create(['slug' => 'public-guide', 'title' => 'مقاله عمومی', 'body' => 'متن', 'is_published' => true, 'published_at' => now()->subDay()]);
        \App\Modules\Website\Infrastructure\WebsiteArticle::create(['slug' => 'private-guide', 'title' => 'مقاله خصوصی', 'body' => 'متن', 'is_published' => false]);
        $blocks = [];
        foreach (array_diff(\App\Modules\Website\Application\PageSchema::TYPES, ['image']) as $type) {
            $block = $this->block(['type' => $type]);
            if (in_array($type, ['features', 'faq'], true)) {
                $block['items'] = [['title' => 'پرسش یا ویژگی', 'text' => 'شرح']];
            }
            $blocks[] = $block;
        }
        $this->save($page, $blocks)->assertOk();
        app(PageService::class)->publication($page, 2, $admin->id, true);
        auth()->logout();
        $this->get('/pages/guide')->assertOk()->assertSee('خدمت قابل رزرو')->assertDontSee('خدمت آفلاین')->assertSee('مقاله عمومی')->assertDontSee('مقاله خصوصی')->assertSee(route('website.services.show', $service), false);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_csrf_cannot_be_bypassed_for_page_and_logo_mutations(): void
    {
        $this->editor();
        $page = $this->page();
        $this->app->instance('env', 'local');
        $this->post(route('website.pages.store'), [])->assertStatus(419);
        $this->put(route('website.pages.update', $page), [])->assertStatus(419);
        $this->post(route('website.pages.publish', $page), [])->assertStatus(419);
        $this->delete(route('website.pages.destroy', $page), [])->assertStatus(419);
        $this->post(route('website.pages.assets.store', $page), [])->assertStatus(419);
        $this->post(route('website.media.store', ['brand', 1]), [])->assertStatus(419);
    }
}
