<?php

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app->instance(App\Modules\Identity\Domain\SmsProviderInterface::class, new Tests\Support\FakeSmsProvider());
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
fwrite(STDOUT, "READY\n");
while (!file_exists($input['barrier'])) {
    usleep(10000);
}
try {
    $service = $app->make(App\Modules\Identity\Application\AuthService::class);
    if ($input['operation'] === 'verify') {
        $service->verify($input['mobile'], $input['id'], 'login', $input['code']);
    } else {
        $service->requestCode($input['mobile'], 'login');
    }
    fwrite(STDOUT, "SUCCESS\n");
} catch (App\Modules\Identity\Domain\OtpFailure $error) {
    fwrite(STDOUT, $error->errorCode."\n");
}
