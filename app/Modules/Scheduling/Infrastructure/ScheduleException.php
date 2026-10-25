<?php

namespace App\Modules\Scheduling\Infrastructure;

use Illuminate\Database\Eloquent\Model;

final class ScheduleException extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['shifts' => 'array'];
    }
}
