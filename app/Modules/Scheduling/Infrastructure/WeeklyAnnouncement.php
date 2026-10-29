<?php

namespace App\Modules\Scheduling\Infrastructure;

use Illuminate\Database\Eloquent\Model;

final class WeeklyAnnouncement extends Model
{
    protected $fillable = ['source_snapshot', 'presence_override', 'location', 'outside_office', 'notes'];

    protected function casts(): array
    {
        return ['source_snapshot' => 'array', 'presence_override' => 'array', 'outside_office' => 'boolean', 'version' => 'integer'];
    }
}
