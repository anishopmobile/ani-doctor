<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('testing') || !str_starts_with(config('database.connections.mysql.database'), 'ani_doctor_test')) {
    throw new RuntimeException('SYNTHETIC_TEST_DATABASE_REQUIRED');
}
$job = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$actor = App\Modules\Identity\Infrastructure\User::findOrFail($job['actor']);
$patient = App\Modules\Patients\Infrastructure\Patient::findOrFail($job['patient']);
$record = isset($job['record']) ? App\Modules\MedicalRecords\Infrastructure\ClinicalRecord::findOrFail($job['record']) : null;
echo "READY\n";
while (!file_exists($job['barrier'])) {
    usleep(10000);
}
try {
    app(App\Modules\MedicalRecords\Application\ClinicalService::class)->save($actor, $patient, 'visit', $job['data'], $record);
    echo "SUCCESS\n";
} catch (Symfony\Component\HttpKernel\Exception\HttpException $error) {
    echo 'HTTP_'.$error->getStatusCode()."\n";
}
