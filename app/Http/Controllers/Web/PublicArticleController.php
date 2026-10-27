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
            'articles' => $articles->publicQuery()->orderByDesc('published_at')->paginate(12),
        ]);
    }

    public function show(string $slug, ArticleService $articles): View
    {
        $article = $articles->publicQuery()->where('slug', $slug)->firstOrFail();

        return view('website.articles.show', ['article' => $article]);
    }
}
