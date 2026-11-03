<?php

namespace App\Modules\MedicalRecords\Infrastructure;

use Illuminate\Database\Eloquent\Model;

final class ClinicalRecord extends Model
{
    protected $table = 'emr_records';
    protected $guarded = ['id'];
    protected $hidden = ['payload', 'reason', 'request_hash'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'reason' => 'encrypted', 'occurred_at' => 'immutable_datetime', 'signed_at' => 'immutable_datetime', 'version' => 'integer'];
    }
}
