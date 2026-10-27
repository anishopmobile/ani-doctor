<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Infrastructure\Service;
use Illuminate\View\View;

final class WebsiteController extends Controller
{
    public function home(\App\Modules\Website\Application\SectionService $sections): View
    {
        return view('website.home', [
            'sections' => $sections->published(),
            'services' => Service::query()
                ->where('is_active', true)
                ->where('is_online', true)
                ->orderBy('name')
                ->limit(6)
                ->get(['id', 'name']),
        ]);
    }
}
