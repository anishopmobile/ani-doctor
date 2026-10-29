<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('testing') || Illuminate\Support\Facades\DB::getDriverName() !== 'mysql') {
    throw new RuntimeException('MYSQL_TEST_ENVIRONMENT_REQUIRED');
}
$data = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
echo "READY\n";
flush();
$deadline = microtime(true) + 10;
while (!file_exists($data['barrier'])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('BARRIER_TIMEOUT');
    }
    usleep(10000);
}
try {
    app(App\Modules\Scheduling\Application\WeeklyAnnouncementService::class)->save(Carbon\CarbonImmutable::parse('2026-10-25', 'Asia/Tehran'), ['expected_version' => 0, 'mode' => 'manual', 'status' => 'closed', 'outside_office' => false, 'location' => $data['location']], $data['actor']);
    echo "SUCCESS\n";
} catch (Illuminate\Validation\ValidationException) {
    echo "CONFLICT\n";
}
