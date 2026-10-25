<?php

namespace App\Modules\Catalog\Infrastructure;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Service extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'price_irr', 'duration_minutes', 'buffer_minutes', 'capacity', 'is_active', 'is_online'];

    protected function casts(): array
    {
        return ['price_irr' => 'integer', 'duration_minutes' => 'integer', 'buffer_minutes' => 'integer', 'capacity' => 'integer', 'is_active' => 'boolean', 'is_online' => 'boolean'];
    }
}
