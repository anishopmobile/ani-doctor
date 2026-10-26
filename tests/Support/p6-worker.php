<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$job = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
Illuminate\Support\Carbon::setTestNow(Carbon\CarbonImmutable::parse($job['now']));
Carbon\CarbonImmutable::setTestNow(Carbon\CarbonImmutable::parse($job['now']));
config(['notifications.enabled' => true]);
$user = App\Modules\Identity\Infrastructure\User::findOrFail($job['user']);
$actor = new App\Modules\Identity\Domain\BookingActor($user->id, $user->mobile, [], (string) config('app.key'));
$appointment = App\Modules\Booking\Infrastructure\Appointment::findOrFail($job['appointment']);
echo "READY\n";
while (!file_exists($job['barrier'])) {
    usleep(10000);
}
try {
    if ($job['mode'] === 'submit') {
        app(App\Modules\DynamicForms\Application\PrivateFormService::class)->submit($actor, $appointment, $job['form'], $job['data']);
    } elseif ($job['mode'] === 'publish') {
        app(App\Modules\DynamicForms\Application\PrivateFormService::class)->publish($user, $job['form'], $job['data']);
    } else {
        $provider = new class ($job['message']) implements App\Modules\Notifications\Domain\EventSmsProviderInterface {
            public function __construct(private int $message)
            {
            }
            public function sendTemplate(string $recipient, int $templateId, array $parameters): string
            {
                if (Illuminate\Support\Facades\DB::transactionLevel()) {
                    throw new \LogicException('NETWORK_UNDER_LOCK');
                }
                Illuminate\Support\Facades\DB::table('sms_delivery_logs')->insert(['message_id' => $this->message, 'state' => 'fixture_provider_call', 'created_at' => now()]);
                usleep(100000);
                return '999';
            }
            public function queryDelivery(string $messageId): ?string
            {
                return null;
            }
        };
        (new App\Modules\Notifications\Application\MessageDispatcher($provider))->dispatch($job['message']);
    }
    echo "SUCCESS\n";
} catch (Symfony\Component\HttpKernel\Exception\HttpException $error) {
    echo 'HTTP_'.$error->getStatusCode()."\n";
}
