<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Website\Application\ThemeService;
use Illuminate\Http\Response;

final class WebsiteThemeCssController extends Controller
{
    public function __invoke(ThemeService $themes): Response
    {
        $palette = $themes->palette();
        $css = ':root { --pink: '.$palette['primary_color'].'; --purple: '.$palette['text_color'].'; --paper: '.$palette['surface_color'].'; }' . "\n";
        $css .= '.btn-primary-site{background:var(--pink)}.btn-outline-site{border-color:var(--pink);color:var(--pink)}.brand-mark,.eyebrow,.service-card a,.why-grid strong,.service-icon,.contact-cta>span,.hero h1 em{color:var(--pink)}.service-icon{background:var(--paper)}.slide-control{border-color:var(--pink)}';

        return response($css, 200, ['Content-Type' => 'text/css; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }
}
