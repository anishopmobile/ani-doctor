<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest;
use App\Modules\Catalog\Application\CatalogService;
use App\Modules\Catalog\Infrastructure\Service;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

final class ServiceController extends Controller
{
    public function index(): View
    {
        return view('admin.services', ['services' => Service::orderBy('id')->paginate(20)]);
    }

    public function store(ServiceRequest $request, CatalogService $catalog): RedirectResponse
    {
        $catalog->save($request->validated(), $request->user()->id);
        return back()->with('status', 'خدمت ثبت شد.');
    }

    public function update(ServiceRequest $request, Service $service, CatalogService $catalog): RedirectResponse
    {
        $catalog->save($request->validated(), $request->user()->id, $service);
        return back()->with('status', 'خدمت به‌روز شد.');
    }

    public function destroy(Request $request, Service $service, CatalogService $catalog): RedirectResponse
    {
        $catalog->archive($service, $request->user()->id);
        return back()->with('status', 'خدمت بایگانی شد؛ تاریخچه قیمت حفظ می‌شود.');
    }
}
