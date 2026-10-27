<?php

namespace App\Modules\Website\Infrastructure;

use Illuminate\Database\Eloquent\Model;

final class WebsiteSection extends Model
{
    protected $fillable = ['type', 'title', 'subtitle', 'body', 'button_text', 'button_url', 'background', 'sort_order', 'is_published'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_published' => 'boolean'];
    }
}
