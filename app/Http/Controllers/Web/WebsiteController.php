<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Infrastructure\Service;
use Illuminate\View\View;

final class WebsiteController extends Controller
{
    public function home(\App\Modules\Website\Application\SectionService $sections, \App\Modules\Website\Application\LinkService $links): View
    {
        return view('website.home', [
            'sections' => $sections->published(),
            'headerLinks' => $links->published('header'),
            'footerLinks' => $links->published('footer'),
            'services' => Service::query()
                ->where('is_active', true)
                ->where('is_online', true)
                ->orderBy('name')
                ->limit(6)
                ->get(['id', 'name']),
        ]);
    }
}
