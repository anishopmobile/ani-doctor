<?php

namespace Tests\Feature;

use App\Modules\Website\Infrastructure\WebsiteArticle;
use App\Modules\Website\Infrastructure\WebsiteMedia;
use App\Modules\Website\Infrastructure\WebsiteTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

final class PublicArticlePagesTest extends TestCase
{
    use RefreshDatabase;
    use BookingFixtures;

    private function article(string $slug, array $data = []): WebsiteArticle
    {
        return WebsiteArticle::create(array_replace(['slug' => $slug, 'title' => 'عنوان '.$slug, 'summary' => 'خلاصه مطلب', 'body' => 'متن آموزشی', 'is_published' => true, 'published_at' => now()->subDay()], $data));
    }

    public function test_search_category_tag_period_and_sort_work_on_published_data(): void
    {
        $this->travelTo(now()->startOfDay());
        $category = WebsiteTaxonomy::create(['type' => 'category', 'slug' => 'care', 'name' => 'مراقبت']);
        $tag = WebsiteTaxonomy::create(['type' => 'tag', 'slug' => 'guide', 'name' => 'راهنما']);
        $recent = $this->article('recent', ['title' => 'آگاهی سلامت', 'published_at' => now()->subDays(2)]);
        $old = $this->article('old', ['title' => 'بهداشت سلامت', 'published_at' => now()->subDays(45)]);
        foreach ([$recent, $old] as $article) {
            $article->taxonomies()->attach([$category->id, $tag->id]);
        }
        $this->article('unrelated');
        $draft = $this->article('hidden', ['title' => 'PRIVATE-DRAFT سلامت', 'is_published' => false]);
        $draft->taxonomies()->attach($category);
        $this->article('future', ['title' => 'PRIVATE-FUTURE سلامت', 'published_at' => now()->addDay()]);
        $this->get('/articles?q=سلامت&category=care&tag=guide&sort=oldest')->assertOk()->assertSeeInOrder([$old->title, $recent->title])->assertDontSee('PRIVATE-DRAFT')->assertDontSee('PRIVATE-FUTURE')->assertDontSee('عنوان unrelated');
        $this->get('/articles?q=سلامت&period=month')->assertOk()->assertSee($recent->title)->assertDontSee($old->title);
        $this->get('/articles?sort=title&category=care')->assertOk()->assertSeeInOrder([$recent->title, $old->title]);
        $this->get('/articles?category=unknown')->assertOk()->assertSee('مقاله‌ای پیدا نشد');
        $this->article('literal', ['title' => 'پیشرفت 100%_ واقعی']);
        $this->get('/articles?q=100%25_')->assertOk()->assertSee('پیشرفت 100%_ واقعی')->assertDontSee($recent->title);
    }

    public function test_pagination_preserves_validated_filters_and_does_not_show_drafts(): void
    {
        for ($i = 1; $i <= 14; $i++) {
            $this->article('item-'.$i, ['title' => 'گروه '.$i, 'published_at' => now()->subMinutes($i)]);
        }
        $response = $this->get('/articles?q=گروه&sort=oldest&period=year')->assertOk()->assertSee('articles-pagination');
        $items = $response->viewData('articles');
        $this->assertCount(12, $items);
        parse_str(parse_url($items->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertSame(['q' => 'گروه', 'sort' => 'oldest', 'period' => 'year', 'page' => '2'], $query);
        $this->assertCount(2, $this->get('/articles?q=گروه&sort=oldest&period=year&page=2')->assertOk()->viewData('articles'));
    }

    public function test_malformed_filters_are_rejected_and_input_is_escaped(): void
    {
        foreach (['q[]=bad' => 'q', 'category[]=bad' => 'category', 'sort=body' => 'sort', 'period=invalid' => 'period', 'page=-1' => 'page', 'tag=../admin' => 'tag'] as $query => $field) {
            $this->getJson('/articles?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->get('/articles?q='.urlencode('<script>alert(1)</script>'))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_detail_sidebar_counts_related_and_share_links_use_only_public_records(): void
    {
        $category = WebsiteTaxonomy::create(['type' => 'category', 'slug' => 'care', 'name' => 'مراقبت واقعی']);
        $article = $this->article('main', ['author_name' => 'نویسنده ثبت‌شده', 'body' => '<script>BODY-XSS</script>']);
        $related = $this->article('related');
        $draft = $this->article('private', ['is_published' => false]);
        $future = $this->article('future', ['published_at' => now()->addDay()]);
        foreach ([$article, $related, $draft, $future] as $item) {
            $item->taxonomies()->attach($category);
        }
        $other = $this->article('other');
        $response = $this->get('/articles/main')->assertOk()->assertSee('نویسنده ثبت‌شده')->assertSee('&lt;script&gt;BODY-XSS&lt;/script&gt;', false)->assertDontSee('<script>BODY-XSS</script>', false)->assertSee('آخرین مقالات')->assertSee('مقالات مرتبط')->assertDontSee('عنوان private')->assertDontSee('عنوان future');
        $this->assertSame(2, $response->viewData('categories')->sole()->articles_count);
        $this->assertSame([$related->id], $response->viewData('relatedArticles')->modelKeys());
        $this->assertContains($other->id, $response->viewData('latestArticles')->modelKeys());
        $response->assertSee(rawurlencode(route('website.articles.show', 'main')), false);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_image_fallback_handles_absent_missing_and_unsafe_paths_without_exposing_them(): void
    {
        Storage::fake('local');
        $article = $this->article('images');
        $fallback = asset('assets/images/article-default.svg');
        $this->get('/articles/images')->assertOk()->assertSee('src="'.$fallback.'"', false)->assertDontSee('onerror=', false);
        $media = WebsiteMedia::create(['owner_type' => 'article', 'owner_id' => $article->id, 'path' => 'website-media/missing.webp', 'mime_type' => 'image/webp', 'alt_text' => 'توضیح تصویر']);
        $this->get('/articles/images')->assertOk()->assertSee('src="'.$fallback.'"', false);
        $media->update(['path' => '../private-patient.png']);
        $this->get('/articles/images')->assertOk()->assertDontSee('private-patient')->assertSee('src="'.$fallback.'"', false);
        $media->update(['path' => 'website-media/picture.png', 'mime_type' => 'image/png']);
        Storage::disk('local')->put('website-media/picture.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5WQAAAAASUVORK5CYII='));
        $this->get('/articles/images')->assertOk()->assertSee('src="'.route('website.media.show', $media).'"', false)->assertSee('data-fallback-src="'.$fallback.'"', false);
    }

    public function test_author_is_optional_validated_and_managed_with_existing_permissions(): void
    {
        $this->seed();
        $patient = $this->booker('09120000007');
        $payload = ['slug' => 'author', 'title' => 'مقاله مدیریت', 'body' => 'متن', 'is_published' => 1, 'published_at' => now()->subDay()->toDateTimeString(), 'author_name' => 'نام نویسنده'];
        $this->actingAs($patient)->post('/admin/website-articles', $payload)->assertForbidden();
        $admin = $this->booker('09120000008', 'admin');
        $this->actingAs($admin)->post('/admin/website-articles', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $article = WebsiteArticle::sole();
        $this->get('/articles/author')->assertOk()->assertSee('نام نویسنده');
        $this->putJson('/admin/website-articles/'.$article->id, array_replace($payload, ['author_name' => str_repeat('a', 121)]))->assertUnprocessable()->assertJsonValidationErrors('author_name');
        $this->put('/admin/website-articles/'.$article->id, array_replace($payload, ['author_name' => '']))->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/articles/author')->assertOk()->assertDontSee('نام نویسنده');
    }

    public function test_author_migration_rollback_preserves_articles_and_booking_data(): void
    {
        $article = $this->article('migration');
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasColumn('website_articles', 'author_name'));
        $this->assertDatabaseHas('website_articles', ['id' => $article->id, 'body' => 'متن آموزشی']);
        $this->assertTrue(Schema::hasTable('appointments'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasColumn('website_articles', 'author_name'));
    }

    public function test_listing_eager_loads_card_relations_in_bounded_queries(): void
    {
        for ($i = 0; $i < 14; $i++) {
            $this->article('bounded-'.$i);
        }
        DB::enableQueryLog();
        $this->get('/articles')->assertOk();
        $queries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'website_articles') || str_contains($query['query'], 'website_article_taxonomy'));
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(5, $queries->count());
        foreach (['/', '/calendar', '/about'] as $path) {
            $this->get($path)->assertDontSee('assets/articles.css');
        }
    }
}
