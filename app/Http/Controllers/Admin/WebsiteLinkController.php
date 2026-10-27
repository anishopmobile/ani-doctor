<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebsiteLinkRequest;
use App\Modules\Website\Application\LinkService;
use App\Modules\Website\Infrastructure\WebsiteLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class WebsiteLinkController extends Controller
{
    public function index(): View
    {
        return view('admin.website-links', [
            'links' => WebsiteLink::query()->orderBy('placement')->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(WebsiteLinkRequest $request, LinkService $links): RedirectResponse
    {
        $links->save($request->validated());

        return back()->with('status', 'لینک ذخیره شد.');
    }

    public function update(WebsiteLinkRequest $request, WebsiteLink $link, LinkService $links): RedirectResponse
    {
        $links->save($request->validated(), $link);

        return back()->with('status', 'لینک به‌روز شد.');
    }

    public function destroy(WebsiteLink $link, LinkService $links): RedirectResponse
    {
        $links->remove($link);

        return back()->with('status', 'لینک حذف شد.');
    }
}
