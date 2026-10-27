<?php

namespace App\Modules\Website\Infrastructure;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WebsitePage extends Model
{
    protected $fillable = ['slug', 'title', 'meta_description', 'blocks'];

    protected function casts(): array
    {
        return ['blocks' => 'array', 'published_snapshot' => 'array', 'version' => 'integer', 'published_version' => 'integer'];
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(WebsitePageRevision::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(WebsitePageAsset::class);
    }

    public function referencesAsset(int $id, bool $publishedOnly = false): bool
    {
        $blocks = $this->published_snapshot['blocks'] ?? [];
        if (!$publishedOnly) {
            $blocks = array_merge($blocks, $this->blocks ?? []);
        }

        return collect($blocks)->contains(fn ($block) => (int) ($block['asset_id'] ?? 0) === $id);
    }
}
