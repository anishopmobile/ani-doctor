<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$job = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
Illuminate\Support\Carbon::setTestNow(Carbon\CarbonImmutable::parse($job['now']));
Carbon\CarbonImmutable::setTestNow(Carbon\CarbonImmutable::parse($job['now']));
$user = App\Modules\Identity\Infrastructure\User::findOrFail($job['user']);
$permissions = array_values(array_filter(['appointments.manage', 'appointments.override_capacity', 'appointments.override_window', 'desk.view', 'desk.book', 'appointments.urgent', 'attendance.manage', 'patients.manage', 'payments.collect'], fn ($permission) => $user->hasPermission($permission)));
$actor = new App\Modules\Identity\Domain\BookingActor($user->id, $user->mobile, $permissions, (string) config('app.key'));
$appointment = isset($job['appointment']) ? App\Modules\Booking\Infrastructure\Appointment::where('reference', $job['appointment'])->firstOrFail() : null;
echo "READY\n";
while (!file_exists($job['barrier'])) {
    usleep(10000);
}
try {
    match ($job['mode']) {
        'attendance' => app(App\Modules\Booking\Application\AttendanceService::class)->change($actor, $appointment, $job['data']),
        'book' => app(App\Modules\Booking\Application\DeskBookingService::class)->create($actor, $job['data']),
        'cancel' => app(App\Modules\Booking\Application\BookingService::class)->change($actor, $appointment, $job['data'], 'cancel'),
        'collect' => app(App\Modules\Billing\Application\OfflinePaymentService::class)->collect($actor, $appointment, $job['data']['amount_irr'], $job['data']['receipt'], $job['data']['reason'], 'cash'),
        'collect_hold' => app(App\Modules\Billing\Application\OfflinePaymentService::class)->confirmCollectedHold($actor, $appointment, $job['data']['amount_irr'], $job['data']['receipt'], $job['data']['reason'], 'cash'),
    };
    echo "SUCCESS\n";
} catch (App\Modules\Booking\Domain\BookingFailure $error) {
    echo $error->errorCode."\n";
}
