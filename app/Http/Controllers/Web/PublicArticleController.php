<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicArticleRequest;
use App\Modules\Website\Application\ArticleService;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

final class PublicArticleController extends Controller
{
    public function index(PublicArticleRequest $request, ArticleService $articles): View|JsonResponse
    {
        $data = [
            'articles' => $articles->listing($request->validated()),
            'filters' => $request->validated(),
        ];
        if ($request->expectsJson()) {
            return response()->json(['html' => view('website.articles.results', $data)->render()], 200, ['Cache-Control' => 'no-store', 'Vary' => 'Accept']);
        }
        return view('website.articles.index', [...$data, 'categories' => $articles->categories()]);
    }

    public function show(string $slug, ArticleService $articles): View
    {
        $article = $articles->publicQuery()->where('slug', $slug)->firstOrFail();

        $article->load(['taxonomies', 'media']);
        return view('website.articles.show', ['article' => $article, 'categories' => $articles->categories(),
            'latestArticles' => $articles->latest($article->id), 'relatedArticles' => $articles->related($article)]);
    }
}
