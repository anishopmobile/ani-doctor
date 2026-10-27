<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Infrastructure\Service;
use App\Modules\Website\Application\ArticleService;
use Illuminate\Http\Response;

final class SitemapController extends Controller
{
    public function __invoke(ArticleService $articles): Response
    {
        return response()->view('website.sitemap', [
            'pages' => \App\Modules\Website\Infrastructure\WebsitePage::query()->whereNotNull('published_snapshot')->where('slug', '!=', 'home')->orderBy('id')->get(['slug', 'updated_at']),
            'services' => Service::query()
                ->where('is_active', true)
                ->where('is_online', true)
                ->orderBy('id')
                ->get(['id', 'updated_at']),
            'articles' => $articles->publicQuery()
                ->orderBy('id')
                ->get(['slug', 'updated_at']),
        ], 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
