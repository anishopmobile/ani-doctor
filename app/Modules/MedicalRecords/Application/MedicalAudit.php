<?php

namespace App\Modules\MedicalRecords\Application;

use App\Modules\SettingsAudit\Application\AuditService;
use Illuminate\Support\Str;

final class MedicalAudit
{
    public function record(int $actor, string $action, ?int $patient = null, ?int $record = null, string $outcome = 'allowed'): void
    {
        app(AuditService::class)->record($actor, 'emr.'.$action, null, ['patient_id' => $patient, 'record_id' => $record, 'outcome' => $outcome, 'correlation_id' => (string) Str::uuid()]);
    }
}
