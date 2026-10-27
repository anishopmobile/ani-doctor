<?php

namespace App\Modules\Website\Application;

use App\Modules\Catalog\Infrastructure\Service;
use App\Modules\Website\Infrastructure\WebsiteArticle;
use App\Modules\Website\Infrastructure\WebsiteMedia;
use App\Modules\Website\Infrastructure\WebsiteSection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class MediaService
{
    private function owner(string $type, int $id): Model
    {
        abort_if($type === 'brand' && $id !== 1, 404);
        if ($type === 'page_asset') {
            $asset = \App\Modules\Website\Infrastructure\WebsitePageAsset::query()->findOrFail($id);
            \App\Modules\Website\Infrastructure\WebsitePage::query()->whereKey($asset->website_page_id)->lockForUpdate()->firstOrFail();

            return \App\Modules\Website\Infrastructure\WebsitePageAsset::query()->whereKey($id)->lockForUpdate()->firstOrFail();
        }
        $model = match ($type) {
            'article' => WebsiteArticle::class,
            'section' => WebsiteSection::class,
            'service' => Service::class,
            'brand' => \App\Modules\Website\Infrastructure\WebsiteTheme::class,
            default => abort(404),
        };

        return $model::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function save(string $type, int $id, UploadedFile $upload, string $alt): void
    {
        $path = $upload->store('website-media', 'local');
        abort_unless(is_string($path), 500);
        try {
            $oldPath = DB::transaction(function () use ($type, $id, $path, $upload, $alt): ?string {
                $this->owner($type, $id);
                $media = WebsiteMedia::query()->where('owner_type', $type)->where('owner_id', $id)->first();
                if ($type === 'page_asset' && $media !== null) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['image' => 'تصویر صفحه تغییرناپذیر است؛ تصویر تازه بارگذاری و نسخه صفحه را منتشر کنید.']);
                }
                $oldPath = $media?->path;
                $media ??= new WebsiteMedia();
                $media->fill([
                    'owner_type' => $type, 'owner_id' => $id, 'path' => $path,
                    'mime_type' => $upload->getMimeType(), 'alt_text' => $alt,
                ])->save();

                return $oldPath;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        if ($oldPath !== null) {
            Storage::disk('local')->delete($oldPath);
        }
    }

    public function remove(WebsiteMedia $media): void
    {
        DB::transaction(function () use ($media): void {
            $owner = $this->owner($media->owner_type, $media->owner_id);
            if ($media->owner_type === 'page_asset' && $owner->page->referencesAsset($owner->id)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['image' => 'تصویر در پیش‌نویس یا نسخه منتشرشده استفاده می‌شود؛ ابتدا بلوک را تغییر و نسخه جدید را منتشر کنید.']);
            }
            $current = WebsiteMedia::query()->whereKey($media->id)->lockForUpdate()->firstOrFail();
            $path = $current->path;
            $current->delete();
            if ($media->owner_type === 'page_asset') {
                $owner->delete();
            }
            DB::afterCommit(fn () => Storage::disk('local')->delete($path));
        });
    }
}
