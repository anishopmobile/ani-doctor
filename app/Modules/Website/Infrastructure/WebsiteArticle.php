<?php

namespace App\Modules\Website\Infrastructure;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class WebsiteArticle extends Model
{
    protected $fillable = ['slug', 'title', 'summary', 'body', 'meta_description', 'is_published', 'published_at'];

    public function taxonomies(): BelongsToMany
    {
        return $this->belongsToMany(WebsiteTaxonomy::class, 'website_article_taxonomy');
    }

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'published_at' => 'immutable_datetime'];
    }
}
