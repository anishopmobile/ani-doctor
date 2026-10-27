<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebsiteMediaRequest;
use App\Modules\Website\Infrastructure\WebsiteArticle;
use App\Modules\Website\Infrastructure\WebsiteMedia;
use App\Modules\Website\Infrastructure\WebsiteSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class WebsiteMediaController extends Controller
{
    public function store(WebsiteMediaRequest $request, string $type, int $id): RedirectResponse
    {
        abort_unless(in_array($type, ['article', 'section'], true), 404);

        $exists = $type === 'article'
            ? WebsiteArticle::query()->whereKey($id)->exists()
            : WebsiteSection::query()->whereKey($id)->exists();
        abort_unless($exists, 404);

        $upload = $request->file('image');
        $path = $upload->store('website-media', 'local');
        abort_unless(is_string($path), 500);

        try {
            $old = WebsiteMedia::query()->where('owner_type', $type)->where('owner_id', $id)->first();
            $oldPath = $old?->path;
            $media = $old ?? new WebsiteMedia();
            $media->fill([
                'owner_type' => $type,
                'owner_id' => $id,
                'path' => $path,
                'mime_type' => $upload->getMimeType(),
                'alt_text' => $request->validated('alt_text'),
            ]);
            $media->save();

            if ($oldPath !== null && $oldPath !== $path) {
                Storage::disk('local')->delete($oldPath);
            }
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('status', 'تصویر ذخیره شد.');
    }

    public function destroy(Request $request, WebsiteMedia $media): RedirectResponse
    {
        $media->deleteWithFile();

        return back()->with('status', 'تصویر حذف شد.');
    }
}
