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
        abort_if(in_array($type, ['brand', 'portrait', 'favicon'], true) && $id !== 1, 404);
        if ($type === 'page_asset') {
            $asset = \App\Modules\Website\Infrastructure\WebsitePageAsset::query()->findOrFail($id);
            \App\Modules\Website\Infrastructure\WebsitePage::query()->whereKey($asset->website_page_id)->lockForUpdate()->firstOrFail();

            return \App\Modules\Website\Infrastructure\WebsitePageAsset::query()->whereKey($id)->lockForUpdate()->firstOrFail();
        }
        $model = match ($type) {
            'article' => WebsiteArticle::class,
            'section' => WebsiteSection::class,
            'service' => Service::class,
            'brand', 'portrait', 'favicon' => \App\Modules\Website\Infrastructure\WebsiteTheme::class,
            default => abort(404),
        };

        return $model::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function save(string $type, int $id, UploadedFile $upload, string $alt): void
    {
        $path = $upload->store('website-media', 'local');
        abort_unless(is_string($path), 500);
        try {
            DB::transaction(function () use ($type, $id, $path, $upload, $alt): void {
                DB::afterRollBack(fn () => Storage::disk('local')->delete($path));
                $owner = $this->owner($type, $id);
                if (in_array($type, ['brand', 'portrait', 'favicon'], true)) {
                    $flag = $type === 'brand' ? 'logo_is_published' : $type.'_is_published';
                    $values = $owner->brand_settings ?? [];
                    $values[$flag] = false;
                    $values['_version'] = (int) ($values['_version'] ?? 0) + 1;
                    $owner->forceFill(['brand_settings' => $values])->save();
                }
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
                if ($oldPath !== null) {
                    DB::afterCommit(fn () => Storage::disk('local')->delete($oldPath));
                }

            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function remove(WebsiteMedia $media): void
    {
        DB::transaction(function () use ($media): void {
            $owner = $this->owner($media->owner_type, $media->owner_id);
            if (in_array($media->owner_type, ['brand', 'portrait', 'favicon'], true) && $media->ownerIsPublished()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['image' => 'این تصویر در هویت منتشرشده استفاده می‌شود؛ ابتدا انتشار آن را در تنظیمات غیرفعال و ذخیره کنید.']);
            }
            if ($media->owner_type === 'page_asset' && $owner->page->referencesAsset($owner->id)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['image' => 'تصویر در پیش‌نویس یا نسخه منتشرشده استفاده می‌شود؛ ابتدا بلوک را تغییر و نسخه جدید را منتشر کنید.']);
            }
            $current = WebsiteMedia::query()->whereKey($media->id)->lockForUpdate()->firstOrFail();
            $path = $current->path;
            $current->delete();
            if (in_array($media->owner_type, ['brand', 'portrait', 'favicon'], true)) {
                $settings = $owner->brand_settings ?? [];
                $settings['_version'] = (int) ($settings['_version'] ?? 0) + 1;
                $owner->forceFill(['brand_settings' => $settings])->save();
            }
            if ($media->owner_type === 'page_asset') {
                $owner->delete();
            }
            DB::afterCommit(fn () => Storage::disk('local')->delete($path));
        });
    }
}
