<?php

namespace App\Modules\Website\Infrastructure;

use Illuminate\Database\Eloquent\Model;

final class WebsitePageRevision extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'version' => 'integer', 'created_at' => 'immutable_datetime'];
    }
}
