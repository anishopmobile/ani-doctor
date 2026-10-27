<?php

namespace Tests\Feature;

use App\Modules\Catalog\Infrastructure\Service;
use App\Modules\Website\Infrastructure\WebsiteArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WebsiteSitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_sitemap_includes_only_discoverable_services_and_articles(): void
    {
        $live = $this->service('خدمت عمومی');
        $inactive = $this->service('خدمت غیرفعال', false);
        $offline = $this->service('خدمت حضوری', true, false);
        $deleted = $this->service('خدمت حذف‌شده');
        $deleted->delete();

        $article = $this->article('published-guide', true, now()->subDay()->toDateTimeString());
        $this->article('unpublished-guide', false, now()->subDay()->toDateTimeString());
        $this->article('future-guide', true, now()->addDay()->toDateTimeString());
        $this->article('undated-guide', true, null);

        $response = $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('http://www.sitemaps.org/schemas/sitemap/0.9', false)
            ->assertSee(route('website.home'), false)
            ->assertSee(route('website.services.show', $live->id), false)
            ->assertSee(route('website.articles.show', $article->slug), false)
            ->assertDontSee(route('website.services.show', $inactive->id), false)
            ->assertDontSee(route('website.services.show', $offline->id), false)
            ->assertDontSee(route('website.services.show', $deleted->id), false)
            ->assertDontSee('unpublished-guide')
            ->assertDontSee('future-guide')
            ->assertDontSee('undated-guide')
            ->assertDontSee('/admin/')
            ->assertDontSee('/api/v1/')
            ->assertDontSee('/appointments/');

        $this->assertStringStartsWith('<?xml', trim($response->getContent()));
    }

    public function test_public_pages_advertise_sitemap_and_canonical_urls(): void
    {
        $this->get('/')->assertOk()
            ->assertSee(route('website.sitemap'), false)
            ->assertSee('<link rel="canonical"', false);

        $this->get('/articles')->assertOk()
            ->assertSee('<link rel="canonical"', false);

        $this->get('/articles?category=test')->assertOk()
            ->assertDontSee('<link rel="canonical"', false);
    }

    private function service(string $name, bool $active = true, bool $online = true): Service
    {
        return Service::create([
            'name' => $name, 'price_irr' => 750000, 'duration_minutes' => 30,
            'buffer_minutes' => 0, 'capacity' => 1, 'is_active' => $active, 'is_online' => $online,
        ]);
    }

    private function article(string $slug, bool $published, ?string $date): WebsiteArticle
    {
        return WebsiteArticle::create([
            'slug' => $slug, 'title' => $slug, 'body' => 'متن مقاله',
            'is_published' => $published, 'published_at' => $date,
        ]);
    }
}
