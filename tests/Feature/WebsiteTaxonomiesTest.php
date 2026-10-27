<?php

namespace Tests\Feature;

use App\Modules\Website\Application\ArticleService;
use App\Modules\Website\Infrastructure\WebsiteArticle;
use App\Modules\Website\Infrastructure\WebsiteTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WebsiteTaxonomiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_and_tags_filter_only_published_articles(): void
    {
        $category = WebsiteTaxonomy::create(['type' => 'category', 'slug' => 'care', 'name' => 'مراقبت']);
        $tag = WebsiteTaxonomy::create(['type' => 'tag', 'slug' => 'women', 'name' => 'زنان']);
        $article = $this->article('visible', true);
        $article->taxonomies()->sync([$category->id, $tag->id]);
        $draft = $this->article('draft-private-article', false);
        $draft->taxonomies()->sync([$category->id]);

        $this->get('/articles?category=care')->assertOk()->assertSee('visible')->assertDontSee('draft-private-article');
        $this->get('/articles?tag=women')->assertOk()->assertSee('visible')->assertDontSee('draft-private-article');
        $this->get('/articles?category=missing')->assertOk()->assertDontSee('visible');
        $this->get(route('website.articles.show', 'visible'))->assertOk()->assertSee('مراقبت')->assertSee('زنان');
    }

    public function test_saving_article_syncs_only_existing_valid_taxonomy_ids(): void
    {
        $tag = WebsiteTaxonomy::create(['type' => 'tag', 'slug' => 'health', 'name' => 'سلامتی']);
        $article = app(ArticleService::class)->save([
            'slug' => 'article-with-tag', 'title' => 'آموزش', 'body' => 'متن آزمایشی',
            'is_published' => false, 'taxonomy_ids' => [$tag->id],
        ]);
        $this->assertSame([$tag->id], $article->taxonomies()->pluck('website_taxonomies.id')->all());

        $rules = (new \App\Http\Requests\WebsiteArticleRequest())->rules();
        $base = ['slug' => 'new-article', 'title' => 'عنوان', 'body' => 'متن', 'is_published' => false];
        $this->assertFalse(validator($base + ['taxonomy_ids' => [999999]], $rules)->passes());
        $this->assertFalse(validator($base + ['taxonomy_ids' => [$tag->id, $tag->id]], $rules)->passes());

        app(ArticleService::class)->save($base + ['taxonomy_ids' => []], $article);
        $this->assertDatabaseCount('website_article_taxonomy', 0);
    }

    public function test_guest_cannot_manage_taxonomies_and_deleting_tag_keeps_article(): void
    {
        $this->get('/admin/website-taxonomies')->assertRedirect('/login');
        $this->post('/admin/website-taxonomies', [])->assertRedirect('/login');

        $tag = WebsiteTaxonomy::create(['type' => 'tag', 'slug' => 'tag-one', 'name' => 'آزمایشی']);
        $article = $this->article('retained-article', true);
        $article->taxonomies()->sync([$tag->id]);
        $tag->delete();
        $this->assertDatabaseHas('website_articles', ['id' => $article->id]);
        $this->assertDatabaseCount('website_article_taxonomy', 0);
    }

    private function article(string $slug, bool $published): WebsiteArticle
    {
        return WebsiteArticle::create([
            'slug' => $slug,
            'title' => $slug,
            'body' => 'متن',
            'is_published' => $published,
            'published_at' => now()->subDay(),
        ]);
    }
}
