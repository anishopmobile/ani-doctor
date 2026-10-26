<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$job = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
Illuminate\Support\Carbon::setTestNow(Carbon\CarbonImmutable::parse($job['now']));
Carbon\CarbonImmutable::setTestNow(Carbon\CarbonImmutable::parse($job['now']));
$user = App\Modules\Identity\Infrastructure\User::findOrFail($job['user']);
$permissions = array_values(array_filter(['appointments.manage', 'payments.collect', 'patients.manage', 'desk.book'], fn ($p) => $user->hasPermission($p)));
$actor = new App\Modules\Identity\Domain\BookingActor($user->id, $user->mobile, $permissions, (string) config('app.key'));
$appointment = isset($job['appointment']) ? App\Modules\Booking\Infrastructure\Appointment::findOrFail($job['appointment']) : null;
echo "READY\n";
while (!file_exists($job['barrier'])) {
    usleep(10000);
}
try {
    match ($job['mode']) {
        'book' => app(App\Modules\Booking\Application\BookingService::class)->create($actor, $job['data']),
        'expire' => app(App\Modules\Booking\Application\BookingService::class)->expire($appointment->id),
        'collect' => app(App\Modules\Billing\Application\OfflinePaymentService::class)->confirmCollectedHold($actor, $appointment, $appointment->price_irr, $job['data']['receipt'], 'رسید ساختگی تست', 'cash'),
    };
    echo "SUCCESS\n";
} catch (App\Modules\Booking\Domain\BookingFailure $error) {
    echo $error->errorCode."\n";
}
