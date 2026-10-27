<?php

namespace App\Modules\Website\Infrastructure;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class WebsiteTaxonomy extends Model
{
    protected $table = 'website_taxonomies';

    protected $fillable = ['type', 'slug', 'name'];

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(WebsiteArticle::class, 'website_article_taxonomy');
    }
}
