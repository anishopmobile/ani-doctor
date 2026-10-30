<?php

require __DIR__.'/panel-browser-seed.php';

$patient = App\Modules\Patients\Infrastructure\Patient::firstOrCreate(['name' => 'بیمار کاملاً ساختگی EMR مرورگر']);
if (!App\Modules\MedicalRecords\Infrastructure\MedicalProfile::where('patient_id', $patient->id)->exists()) {
    app(App\Modules\MedicalRecords\Application\ProfileService::class)->save($admin, ['name' => $patient->name, 'identity_type' => 'unknown', 'marital_status' => 'unknown', 'status' => 'active', 'expected_version' => 0], $patient);
}
echo 'EMR_PATIENT='.$patient->id.PHP_EOL;
