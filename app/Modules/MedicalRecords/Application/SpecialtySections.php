<?php

namespace App\Modules\MedicalRecords\Application;

use App\Modules\MedicalRecords\Infrastructure\ClinicalRecord;
use App\Modules\MedicalRecords\Infrastructure\MedicalProfile;
use App\Modules\Patients\Infrastructure\Patient;

final class SpecialtySections
{
    public const KINDS = ['menstrual', 'pregnancy', 'followup', 'fertility'];

    public function visible(Patient $patient, ?MedicalProfile $profile): array
    {
        // Existing records remain discoverable; visibility is not a medical inference.
        $existing = ClinicalRecord::where('patient_id', $patient->id)->whereIn('kind', self::KINDS)->distinct()->pluck('kind')->all();
        $enabled = array_intersect(self::KINDS, $profile?->identity['enabled_sections'] ?? []);
        if (in_array('pregnancy', [...$existing, ...$enabled], true)) {
            $enabled[] = 'followup';
        }
        return array_diff_key(ClinicalSchema::labels(), array_flip(array_diff(self::KINDS, [...$existing, ...$enabled])));
    }
}
