<?php

namespace App\Modules\Patients\Infrastructure;

use Illuminate\Database\Eloquent\Model;

final class Patient extends Model
{
    protected $fillable = ['name', 'contact_mobile', 'file_number'];
}
