<?php

namespace App\Modules\Website\Infrastructure;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class WebsiteSection extends Model
{
    protected $fillable = ['type', 'title', 'subtitle', 'body', 'button_text', 'button_url', 'background', 'sort_order', 'is_published'];

    public function media(): HasOne
    {
        return $this->hasOne(WebsiteMedia::class, 'owner_id')->where('owner_type', 'section');
    }

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_published' => 'boolean'];
    }
}
