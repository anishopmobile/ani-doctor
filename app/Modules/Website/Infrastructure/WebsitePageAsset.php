<?php

namespace App\Modules\Website\Infrastructure;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class WebsitePageAsset extends Model
{
    protected $fillable = ['website_page_id'];

    public function page(): BelongsTo
    {
        return $this->belongsTo(WebsitePage::class, 'website_page_id');
    }

    public function media(): HasOne
    {
        return $this->hasOne(WebsiteMedia::class, 'owner_id')->where('owner_type', 'page_asset');
    }
}
