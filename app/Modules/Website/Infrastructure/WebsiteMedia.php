<?php

namespace App\Modules\Website\Infrastructure;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

final class WebsiteMedia extends Model
{
    protected $table = 'website_media';

    protected $fillable = ['owner_type', 'owner_id', 'path', 'mime_type', 'alt_text'];

    public function ownerIsPublished(): bool
    {
        if ($this->owner_type === 'page_asset') {
            $asset = WebsitePageAsset::query()->with('page')->find($this->owner_id);

            return $asset?->page?->referencesAsset($asset->id, true) ?? false;
        }
        if ($this->owner_type === 'brand') {
            $theme = WebsiteTheme::query()->find($this->owner_id);

            return (int) $this->owner_id === 1 && ($theme?->brand_settings['logo_is_published'] ?? false) === true;
        }
        if ($this->owner_type === 'service') {
            return \App\Modules\Catalog\Infrastructure\Service::query()
                ->whereKey($this->owner_id)->where('is_active', true)->where('is_online', true)
                ->whereHas('websiteContent', fn ($query) => $query->where('is_published', true))->exists();
        }

        if ($this->owner_type === 'article') {
            return WebsiteArticle::query()
                ->whereKey($this->owner_id)
                ->where('is_published', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->exists();
        }

        if ($this->owner_type === 'section') {
            return WebsiteSection::query()
                ->whereKey($this->owner_id)
                ->where('is_published', true)
                ->exists();
        }

        return false;
    }

    public function deleteWithFile(): void
    {
        $path = $this->path;
        $this->delete();
        Storage::disk('local')->delete($path);
    }
}
