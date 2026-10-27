<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebsiteMediaRequest;
use App\Modules\Website\Application\MediaService;
use App\Modules\Website\Infrastructure\WebsiteMedia;
use Illuminate\Http\RedirectResponse;

final class WebsiteMediaController extends Controller
{
    public function store(WebsiteMediaRequest $request, string $type, int $id, MediaService $media): RedirectResponse
    {
        $media->save($type, $id, $request->file('image'), $request->validated('alt_text'));

        return back()->with('status', 'تصویر ذخیره شد.');
    }

    public function destroy(WebsiteMedia $media, MediaService $service): RedirectResponse
    {
        $service->remove($media);

        return back()->with('status', 'تصویر حذف شد.');
    }
}
