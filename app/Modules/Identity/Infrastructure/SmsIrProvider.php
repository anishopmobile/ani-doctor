<?php

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Domain\SmsProviderInterface;
use Illuminate\Support\Facades\Http;

final class SmsIrProvider implements SmsProviderInterface
{
    public function sendOtp(string $mobile, #[\SensitiveParameter] string $code): void
    {
        $settings = config('identity.sms_ir');
        if (!$settings['api_key'] || !ctype_digit((string) $settings['otp_template_id'])) {
            throw new \RuntimeException('SMS_NOT_CONFIGURED');
        }
        // No retry: a timed-out request may already have delivered the message.
        $response = Http::acceptJson()->withHeaders(['X-API-KEY' => $settings['api_key']])
            ->connectTimeout(3)->timeout(8)->post('https://api.sms.ir/v1/send/verify', [
                'mobile' => $mobile,
                'templateId' => (int) $settings['otp_template_id'],
                'parameters' => [['name' => $settings['otp_parameter'], 'value' => $code]],
            ]);
        if (!$response->successful() || $response->json('status') !== 1) {
            throw new \RuntimeException('SMS_DELIVERY_FAILED');
        }
    }
}
