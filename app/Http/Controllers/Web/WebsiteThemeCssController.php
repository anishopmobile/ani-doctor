<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Website\Application\ThemeService;
use Illuminate\Http\Response;

final class WebsiteThemeCssController extends Controller
{
    public function __invoke(ThemeService $themes): Response
    {
        return response($themes->css(), 200, ['Content-Type' => 'text/css; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-cache']);
    }
}
