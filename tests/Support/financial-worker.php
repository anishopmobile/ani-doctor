<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$job = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$app->instance(App\Modules\Payments\Domain\PaymentGatewayInterface::class, new Tests\Support\FakePaymentGateway());
config(['payments.zarinpal' => ['enabled' => true, 'merchant_id' => '00000000-0000-4000-8000-000000000000', 'sandbox' => true, 'callback_url' => 'https://clinic.example/payments/zarinpal/callback']]);
$user = App\Modules\Identity\Infrastructure\User::findOrFail($job['user']);
$permissions = array_values(array_filter(['appointments.manage', 'appointments.override_window', 'payments.collect', 'payments.settings', 'refunds.manage'], fn ($permission) => $user->hasPermission($permission)));
$actor = new App\Modules\Identity\Domain\BookingActor($user->id, $user->mobile, $permissions, (string) config('app.key'));
$appointment = isset($job['appointment']) ? App\Modules\Booking\Infrastructure\Appointment::where('reference', $job['appointment'])->firstOrFail() : null;
$attempt = isset($job['attempt']) ? App\Modules\Payments\Infrastructure\PaymentAttempt::where('reference', $job['attempt'])->firstOrFail() : null;
echo "READY\n";
while (!file_exists($job['barrier'])) {
    usleep(10000);
}
try {
    match ($job['mode']) {
        'callback' => app(App\Modules\Payments\Application\PaymentService::class)->callback($attempt->reference, $attempt->authority, 'OK'),
        'initiate' => app(App\Modules\Payments\Application\PaymentService::class)->initiate($actor, $appointment, $job['data']),
        'cancel' => app(App\Modules\Booking\Application\BookingService::class)->change($actor, $appointment, $job['data'], 'cancel'),
        'refund' => app(App\Modules\Wallet\Application\RefundService::class)->request($actor, $job['data']['amount_irr'], $job['data']['idempotency_key'], $job['data']['reason']),
        'decision' => app(App\Modules\Wallet\Application\RefundService::class)->decide($actor, $job['refund'], $job['data']['decision'], $job['data']['reason'], $job['data']['bank_reference'] ?? null),
        'collect' => app(App\Modules\Billing\Application\OfflinePaymentService::class)->collect($actor, $appointment, $job['data']['amount_irr'], $job['data']['receipt'], $job['data']['reason'], 'cash'),
        'expire' => app(App\Modules\Booking\Application\BookingService::class)->expire($appointment->id),
    };
    echo "SUCCESS\n";
} catch (App\Modules\Booking\Domain\BookingFailure $error) {
    echo $error->errorCode."\n";
}
