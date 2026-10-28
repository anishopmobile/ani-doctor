<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebsiteThemeRequest;
use App\Modules\Website\Application\ThemeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class WebsiteThemeController extends Controller
{
    public function edit(ThemeService $themes): View
    {
        return view('admin.website-theme', ['theme' => $themes->settings(), 'themeRecord' => \App\Modules\Website\Infrastructure\WebsiteTheme::query()->with(['media', 'portraitMedia', 'faviconMedia'])->find(1), 'homePage' => \App\Modules\Website\Infrastructure\WebsitePage::query()->where('slug', 'home')->whereNotNull('published_snapshot')->first(), 'hasPublishedSlides' => \App\Modules\Website\Infrastructure\WebsiteSection::query()->where('type', 'slide')->where('is_published', true)->exists()]);
    }

    public function update(WebsiteThemeRequest $request, ThemeService $themes): RedirectResponse
    {
        $themes->save($request->validated());

        return redirect()->route('website.theme.edit')->with('status', 'تنظیمات سایت ذخیره شد.');
    }

    public function preview(WebsiteThemeRequest $request, ThemeService $themes): \Illuminate\Http\Response
    {
        $settings = \App\Modules\Website\Application\SiteContent::sanitize(array_replace($themes->settings(), $request->validated()));
        $request->session()->put('website_theme_preview', $settings);

        return response()->view('admin.website-theme-preview', ['themePreview' => $settings, 'isThemePreview' => true, 'isPreview' => true])->header('Cache-Control', 'private, no-store');
    }

    public function previewCss(\Illuminate\Http\Request $request, ThemeService $themes): \Illuminate\Http\Response
    {
        abort_unless(is_array($request->session()->get('website_theme_preview')), 404);

        return response($themes->css($request->session()->get('website_theme_preview')), 200, ['Content-Type' => 'text/css; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'Vary' => 'Cookie', 'X-Content-Type-Options' => 'nosniff']);
    }
}
