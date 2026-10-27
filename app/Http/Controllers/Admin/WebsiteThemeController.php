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
        return view('admin.website-theme', ['theme' => $themes->settings()]);
    }

    public function update(WebsiteThemeRequest $request, ThemeService $themes): RedirectResponse
    {
        $themes->save($request->validated());

        return back()->with('status', 'رنگ‌بندی سایت ذخیره شد.');
    }
}
