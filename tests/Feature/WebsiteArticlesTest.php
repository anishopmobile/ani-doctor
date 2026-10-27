<?php

namespace Tests\Feature;

use App\Modules\Website\Infrastructure\WebsiteArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WebsiteArticlesTest extends TestCase
{
    use RefreshDatabase;

    private function article(string $slug, bool $published, ?string $date): WebsiteArticle
    {
        return WebsiteArticle::create([
            'slug' => $slug,
            'title' => 'مقاله '. $slug,
            'summary' => 'خلاصه متن',
            'body' => '<script>alert(1)</script>',
            'meta_description' => 'توضیحات سایت',
            'is_published' => $published,
            'published_at' => $date,
        ]);
    }

    public function test_only_published_articles_whose_date_has_arrived_are_public(): void
    {
        $live = $this->article('visible-article', true, now()->subDay()->toDateTimeString());
        $this->article('draft-article', false, now()->subDay()->toDateTimeString());
        $this->article('scheduled-article', true, now()->addDay()->toDateTimeString());
        $this->article('undated-article', true, null);

        $this->get('/articles')->assertOk()
            ->assertSee($live->title)
            ->assertDontSee('draft-article')
            ->assertDontSee('scheduled-article')
            ->assertDontSee('undated-article');

        foreach (['draft-article', 'scheduled-article', 'undated-article', 'missing-article'] as $slug) {
            $this->get('/articles/'.$slug)->assertNotFound();
        }
    }

    public function test_article_html_is_displayed_as_text_not_executable_markup(): void
    {
        $article = $this->article('safe-article', true, now()->subDay()->toDateTimeString());

        $this->get(route('website.articles.show', $article->slug))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('توضیحات سایت');
    }

    public function test_guest_cannot_open_article_administration_or_submit_content(): void
    {
        $this->get('/admin/website-articles')->assertRedirect('/login');
        $this->post('/admin/website-articles', [])->assertRedirect('/login');
    }

    public function test_slug_must_be_unique_and_safe(): void
    {
        $this->article('used-slug', false, null);
        $request = new \App\Http\Requests\WebsiteArticleRequest();
        $base = [
            'slug' => 'new-slug', 'title' => 'عنوان آزمایشی',
            'body' => 'محتوا', 'is_published' => 0,
        ];

        $this->assertTrue(validator($base, $request->rules())->passes());
        foreach (['used-slug', '../admin', 'javascript:alert(1)', '<script>'] as $slug) {
            $this->assertFalse(validator(array_replace($base, ['slug' => $slug]), $request->rules())->passes());
        }
    }
}
