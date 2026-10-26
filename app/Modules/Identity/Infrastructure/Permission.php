<?php

namespace App\Modules\Identity\Infrastructure;

use Illuminate\Database\Eloquent\Model;

final class Permission extends Model
{
    public $timestamps = false;

    protected $fillable = ['name'];
}
