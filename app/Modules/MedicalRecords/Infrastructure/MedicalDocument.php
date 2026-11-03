<?php

namespace App\Modules\MedicalRecords\Infrastructure;

use Illuminate\Database\Eloquent\Model;

final class MedicalDocument extends Model
{
    protected $table = 'emr_documents';
    protected $guarded = ['id'];
    protected $hidden = ['metadata', 'path', 'sha256'];

    protected function casts(): array
    {
        return ['metadata' => 'encrypted:array', 'reviewed_at' => 'immutable_datetime'];
    }
}
