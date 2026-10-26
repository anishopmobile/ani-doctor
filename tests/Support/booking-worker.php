<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$input = json_decode(stream_get_contents(STDIN), true, flags:JSON_THROW_ON_ERROR);
$user = App\Modules\Identity\Infrastructure\User::findOrFail($input['user']);
$permissions = array_values(array_filter(['appointments.manage','appointments.override_capacity','appointments.override_window'], fn ($p) => $user->hasPermission($p)));
$actor = new App\Modules\Identity\Domain\BookingActor($user->id, $user->mobile, $permissions, (string)config('app.key'));
$snapshot = isset($input['reference']) ? App\Modules\Booking\Infrastructure\Appointment::where('reference', $input['reference'])->sole() : null;
fwrite(STDOUT, "READY\n");
while (!file_exists($input['barrier'])) {
    usleep(10000);
}
try {
    $service = $app->make(App\Modules\Booking\Application\BookingService::class);
    if ($input['mode'] === 'expire') {
        $service->expire($snapshot->id);
        $result = $snapshot;
    } elseif ($input['mode'] === 'create') {
        $result = $service->create($actor, $input['data']);
    } else {
        $result = $service->change($actor, $snapshot, $input['data'], $input['mode']);
    }
    fwrite(STDOUT, "SUCCESS ".$result->reference."\n");
} catch (App\Modules\Booking\Domain\BookingFailure $error) {
    fwrite(STDOUT, $error->errorCode."\n");
}
