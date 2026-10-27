<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Website\Application\ArticleService;
use Illuminate\View\View;

final class PublicArticleController extends Controller
{
    public function index(ArticleService $articles): View
    {
        return view('website.articles.index', [
            'articles' => $articles->publicQuery()->when(request('category'), function ($query, $slug): void {
                $query->whereHas('taxonomies', fn ($taxonomies) => $taxonomies->where('type', 'category')->where('slug', $slug));
            })->when(request('tag'), function ($query, $slug): void {
                $query->whereHas('taxonomies', fn ($taxonomies) => $taxonomies->where('type', 'tag')->where('slug', $slug));
            })->with(['taxonomies', 'media'])->orderByDesc('published_at')->paginate(12)->withQueryString(),
        ]);
    }

    public function show(string $slug, ArticleService $articles): View
    {
        $article = $articles->publicQuery()->where('slug', $slug)->firstOrFail();

        return view('website.articles.show', ['article' => $article->load(['taxonomies', 'media'])]);
    }
}
