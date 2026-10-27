<?php

namespace App\Modules\Website\Infrastructure;

use Illuminate\Database\Eloquent\Model;

final class WebsiteLink extends Model
{
    protected $fillable = ['placement', 'label', 'url', 'sort_order', 'is_published', 'new_tab'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_published' => 'boolean', 'new_tab' => 'boolean'];
    }
}
