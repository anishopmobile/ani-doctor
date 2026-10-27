<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebsiteServiceContentRequest;
use App\Modules\Catalog\Infrastructure\Service;
use App\Modules\Website\Application\ServiceContentService;
use App\Modules\Website\Infrastructure\WebsiteServiceContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class WebsiteServiceContentController extends Controller
{
    public function edit(int $service): View
    {
        $item = Service::query()->findOrFail($service);

        return view('admin.website-service-content', [
            'service' => $item,
            'content' => WebsiteServiceContent::query()->where('service_id', $item->id)->first(),
        ]);
    }

    public function update(WebsiteServiceContentRequest $request, int $service, ServiceContentService $contents): RedirectResponse
    {
        $item = Service::query()->findOrFail($service);
        $contents->save($item->id, $request->validated());

        return back()->with('status', 'محتوای اختصاصی خدمت ذخیره شد.');
    }
}
