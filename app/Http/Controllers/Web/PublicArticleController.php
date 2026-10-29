<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicArticleRequest;
use App\Modules\Website\Application\ArticleService;
use Illuminate\View\View;

final class PublicArticleController extends Controller
{
    public function index(PublicArticleRequest $request, ArticleService $articles): View
    {
        return view('website.articles.index', [
            'articles' => $articles->listing($request->validated()),
            'filters' => $request->validated(),
            'categories' => $articles->categories(),
        ]);
    }

    public function show(string $slug, ArticleService $articles): View
    {
        $article = $articles->publicQuery()->where('slug', $slug)->firstOrFail();

        $article->load(['taxonomies', 'media']);
        return view('website.articles.show', ['article' => $article, 'categories' => $articles->categories(),
            'latestArticles' => $articles->latest($article->id), 'relatedArticles' => $articles->related($article)]);
    }
}
