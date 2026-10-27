<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebsiteTaxonomyRequest;
use App\Modules\Website\Infrastructure\WebsiteTaxonomy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class WebsiteTaxonomyController extends Controller
{
    public function index(): View
    {
        return view('admin.website-taxonomies', [
            'taxonomies' => WebsiteTaxonomy::query()->orderBy('type')->orderBy('name')->get(),
        ]);
    }

    public function store(WebsiteTaxonomyRequest $request): RedirectResponse
    {
        WebsiteTaxonomy::create($request->validated());

        return back()->with('status', 'دسته‌بندی یا برچسب ثبت شد.');
    }

    public function destroy(Request $request, WebsiteTaxonomy $taxonomy): RedirectResponse
    {
        $taxonomy->delete();

        return back()->with('status', 'دسته‌بندی یا برچسب حذف شد.');
    }
}
