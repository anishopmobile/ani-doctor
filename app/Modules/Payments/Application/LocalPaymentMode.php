<?php

namespace App\Modules\Payments\Application;

final class LocalPaymentMode
{
    public function enabled(): bool
    {
        $hosts = ['localhost', '127.0.0.1', '::1', '[::1]'];
        return app()->environment('local')
            && config('payments.local_simulator') === true
            && in_array(parse_url((string) config('app.url'), PHP_URL_HOST), $hosts, true)
            && in_array(request()->getHost(), $hosts, true)
            && in_array(request()->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true);
    }

    public function settings(): array
    {
        return [
            'enabled' => true, 'merchant_id' => 'local-simulator', 'sandbox' => true,
            'callback_url' => rtrim((string) config('app.url'), '/').'/payments/zarinpal/callback',
        ];
    }
}
