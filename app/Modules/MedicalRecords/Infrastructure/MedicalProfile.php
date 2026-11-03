<?php

namespace App\Modules\MedicalRecords\Infrastructure;

use Illuminate\Database\Eloquent\Model;

final class MedicalProfile extends Model
{
    protected $table = 'emr_profiles';
    protected $guarded = ['id'];
    protected $hidden = ['identity', 'identity_hash'];

    protected function casts(): array
    {
        return ['identity' => 'encrypted:array', 'version' => 'integer'];
    }
}
