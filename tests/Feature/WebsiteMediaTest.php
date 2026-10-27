<?php

namespace Tests\Feature;

use App\Modules\Identity\Infrastructure\Role;
use App\Modules\Identity\Infrastructure\User;
use App\Modules\Website\Infrastructure\WebsiteArticle;
use App\Modules\Website\Infrastructure\WebsiteMedia;
use App\Modules\Website\Infrastructure\WebsiteSection;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class WebsiteMediaTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::create(['mobile' => '09120000001']);
        $user->forceFill(['mobile_verified_at' => now()])->save();
        $user->roles()->attach(Role::query()->where('name', 'admin')->firstOrFail());

        return $user;
    }

    private function article(bool $published): WebsiteArticle
    {
        return WebsiteArticle::create([
            'slug' => 'medical-guide', 'title' => 'راهنمای پزشکی', 'body' => 'متن',
            'is_published' => $published, 'published_at' => now()->subDay(),
        ]);
    }

    public function test_private_media_is_not_accessible_until_its_article_is_published(): void
    {
        Storage::fake('local');
        $article = $this->article(false);
        $admin = $this->editor();

        $this->actingAs($admin)->post(route('website.media.store', ['article', $article->id]), [
            'image' => UploadedFile::fake()->image('cover.jpg', 120, 80),
            'alt_text' => 'تصویر آموزشی',
        ])->assertRedirect();

        $media = WebsiteMedia::query()->firstOrFail();
        Storage::disk('local')->assertExists($media->path);

        auth()->logout();
        $this->get(route('website.media.show', $media))->assertNotFound();
        $this->get('/articles')->assertDontSee(route('website.media.show', $media), false);

        $article->update(['is_published' => true]);
        $this->get(route('website.media.show', $media))->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('website.articles.show', $article->slug))->assertSee('تصویر آموزشی');
    }

    public function test_media_upload_rejects_unsafe_file_types_and_guest_requests(): void
    {
        Storage::fake('local');
        $article = $this->article(true);
        $url = route('website.media.store', ['article', $article->id]);

        $this->post($url, ['alt_text' => 'آزمایشی'])->assertRedirect('/login');

        $this->actingAs($this->editor())->post($url, [
            'image' => UploadedFile::fake()->create('danger.svg', 1, 'image/svg+xml'),
            'alt_text' => 'فایل خطرناک',
        ])->assertSessionHasErrors('image');

        $this->assertDatabaseCount('website_media', 0);
    }

    public function test_published_section_media_is_public_and_deleted_with_its_owner(): void
    {
        Storage::fake('local');
        $section = WebsiteSection::create([
            'type' => 'slide', 'title' => 'اسلاید', 'background' => 'plain',
            'sort_order' => 0, 'is_published' => true,
        ]);
        $this->actingAs($this->editor())->post(route('website.media.store', ['section', $section->id]), [
            'image' => UploadedFile::fake()->image('slide.png'),
            'alt_text' => 'عکس اسلاید',
        ])->assertRedirect();

        $media = WebsiteMedia::query()->firstOrFail();
        $this->get('/')->assertSee('عکس اسلاید');

        app(\App\Modules\Website\Application\SectionService::class)->remove($section);
        Storage::disk('local')->assertMissing($media->path);
        $this->assertDatabaseCount('website_media', 0);
        $this->get(route('website.media.show', $media))->assertNotFound();
    }
}
