<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebsiteSectionRequest;
use App\Modules\Website\Application\SectionService;
use App\Modules\Website\Infrastructure\WebsiteSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class WebsiteSectionController extends Controller
{
    public function index(): View
    {
        return view('admin.website-sections', [
            'sections' => WebsiteSection::query()->with('media')->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(WebsiteSectionRequest $request, SectionService $sections): RedirectResponse
    {
        $sections->save($request->validated());

        return back()->with('status', 'سکشن ثبت شد.');
    }

    public function update(WebsiteSectionRequest $request, WebsiteSection $section, SectionService $sections): RedirectResponse
    {
        $sections->save($request->validated(), $section);

        return back()->with('status', 'سکشن ویرایش شد.');
    }

    public function destroy(WebsiteSection $section, SectionService $sections): RedirectResponse
    {
        $sections->remove($section);

        return back()->with('status', 'سکشن حذف شد.');
    }
}
