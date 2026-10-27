<?php

namespace App\Modules\Website\Infrastructure;

use Illuminate\Database\Eloquent\Model;

final class WebsiteServiceContent extends Model
{
    protected $fillable = ['service_id', 'summary', 'description', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }
}
