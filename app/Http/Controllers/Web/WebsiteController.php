<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class WebsiteController extends Controller
{
    public function home(\App\Modules\Website\Application\SectionService $sections, \App\Modules\Website\Application\LinkService $links): View
    {
        $page = \App\Modules\Website\Infrastructure\WebsitePage::query()->where('slug', 'home')->whereNotNull('published_snapshot')->first();
        if ($page) {
            return view('website.pages.show', app(\App\Modules\Website\Application\PageService::class)->render($page));
        }

        return view('website.home', [
            'sections' => $sections->published(),
            'slides' => $sections->published('slide'),
            'headerLinks' => $links->published('header'),
            'footerLinks' => $links->published('footer'),
            'services' => app(\App\Modules\Website\Application\ServiceContentService::class)->catalogQuery()->limit(6)->get(['id', 'name']),
        ]);
    }
}
