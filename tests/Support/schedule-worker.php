<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$input = json_decode(stream_get_contents(STDIN), true, flags:JSON_THROW_ON_ERROR);
fwrite(STDOUT, "READY\n");
while (!file_exists($input['barrier'])) {
    usleep(10000);
}
try {
    $app->make(App\Modules\Scheduling\Application\ScheduleService::class)->saveException([
        'start_date' => '2026-10-28','end_date' => '2026-10-30','kind' => 'closed','shifts' => [],'reason' => 'Test closure'
    ], $input['actor']);
    fwrite(STDOUT, "SUCCESS\n");
} catch (Illuminate\Validation\ValidationException) {
    fwrite(STDOUT, "CONFLICT\n");
}
