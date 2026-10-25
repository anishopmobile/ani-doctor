<?php

namespace App\Modules\Scheduling\Infrastructure;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WeeklySchedule extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    public function shifts(): HasMany
    {
        return $this->hasMany(WorkShift::class)->orderBy('start_minute');
    }
}
