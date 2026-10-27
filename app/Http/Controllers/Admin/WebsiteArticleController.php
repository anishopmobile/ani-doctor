<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebsiteArticleRequest;
use App\Modules\Website\Application\ArticleService;
use App\Modules\Website\Infrastructure\WebsiteArticle;
use App\Modules\Website\Infrastructure\WebsiteTaxonomy;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class WebsiteArticleController extends Controller
{
    public function index(): View
    {
        return view('admin.website-articles', [
            'articles' => WebsiteArticle::query()->with(['taxonomies', 'media'])->orderByDesc('updated_at')->paginate(20),
            'taxonomies' => WebsiteTaxonomy::query()->orderBy('type')->orderBy('name')->get(),
        ]);
    }

    public function store(WebsiteArticleRequest $request, ArticleService $articles): RedirectResponse
    {
        $articles->save($request->validated());

        return back()->with('status', 'مقاله ثبت شد.');
    }

    public function update(WebsiteArticleRequest $request, WebsiteArticle $article, ArticleService $articles): RedirectResponse
    {
        $articles->save($request->validated(), $article);

        return back()->with('status', 'مقاله ویرایش شد.');
    }

    public function destroy(WebsiteArticle $article, ArticleService $articles): RedirectResponse
    {
        $articles->remove($article);

        return back()->with('status', 'مقاله حذف شد.');
    }
}
