<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Website\Application\PageService;
use App\Modules\Website\Infrastructure\WebsitePage;

final class PublicPageController extends Controller
{
    public function __invoke(string $slug, PageService $pages)
    {
        $page = WebsitePage::query()->where('slug', $slug)->whereNotNull('published_snapshot')->firstOrFail();
        if ($slug === 'home') {
            return redirect()->route('website.home', [], 301);
        }

        return view('website.pages.show', $pages->render($page));
    }
}
