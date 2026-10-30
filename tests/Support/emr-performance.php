<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
if (!app()->environment('testing') || config('database.default') !== 'mysql'
    || config('database.connections.mysql.database') !== 'ani_doctor_test_emr_perf') {
    throw new RuntimeException('ISOLATED_EMPTY_EMR_PERFORMANCE_DATABASE_REQUIRED');
}
if (Illuminate\Support\Facades\Schema::hasTable('patients') && App\Modules\Patients\Infrastructure\Patient::exists()) {
    throw new RuntimeException('PERFORMANCE_DATABASE_NOT_EMPTY');
}
$kernel->call('migrate', ['--force' => true]);
for ($batch = 0; $batch < 20; $batch++) {
    $rows = [];
    for ($item = 0; $item < 500; $item++) {
        $number = $batch * 500 + $item;
        $rows[] = ['name' => sprintf('EMR PERF SYNTHETIC %05d', $number), 'file_number' => 'EMR-PERF-'.$number, 'created_at' => now(), 'updated_at' => now()];
    }
    Illuminate\Support\Facades\DB::table('patients')->insert($rows);
}
$profiles = app(App\Modules\MedicalRecords\Application\ProfileService::class);
$report = ['rows' => App\Modules\Patients\Infrastructure\Patient::count()];
foreach (['file_number' => 'EMR-PERF-9999', 'name_contains' => 'SYNTHETIC 099'] as $mode => $query) {
    $times = [];
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $start = microtime(true);
        $result = $profiles->search(['query' => $query])->paginate(20);
        $times[] = (microtime(true) - $start) * 1000;
    }
    sort($times);
    if ($result->total() !== ($mode === 'file_number' ? 1 : 100)) {
        throw new RuntimeException('PERFORMANCE_RESULT_MISMATCH');
    }
    $builder = $profiles->search(['query' => $query]);
    $plan = Illuminate\Support\Facades\DB::select('EXPLAIN '.$builder->toSql(), $builder->getBindings());
    $report[$mode] = ['p95_ms' => round($times[18], 2), 'returned' => $result->count(), 'total' => $result->total(), 'plan' => array_map(fn ($row) => ['table' => $row->table, 'type' => $row->type, 'key' => $row->key, 'rows' => $row->rows], $plan)];
}
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
