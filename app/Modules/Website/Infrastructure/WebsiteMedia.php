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
