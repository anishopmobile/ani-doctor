<?php

namespace App\Modules\Website\Infrastructure;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class WebsiteArticle extends Model
{
    protected $fillable = ['slug', 'title', 'summary', 'body', 'meta_description', 'author_name', 'is_published', 'published_at'];

    public function taxonomies(): BelongsToMany
    {
        return $this->belongsToMany(WebsiteTaxonomy::class, 'website_article_taxonomy');
    }

    public function media(): HasOne
    {
        return $this->hasOne(WebsiteMedia::class, 'owner_id')->where('owner_type', 'article');
    }

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'published_at' => 'immutable_datetime'];
    }
}
