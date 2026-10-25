<?php

namespace App\Modules\Notifications\Infrastructure;

use App\Modules\Notifications\Domain\EventSmsProviderInterface;
use App\Modules\Notifications\Domain\SmsFailure;
use Illuminate\Support\Facades\Http;

final class SmsIrEventProvider implements EventSmsProviderInterface
{
    public function sendTemplate(#[\SensitiveParameter] string $recipient, int $templateId, array $parameters): string
    {
        $key = config('identity.sms_ir.api_key');
        if (!$key) {
            throw new SmsFailure(true); // No request made.
        }
        try {
            $response = Http::acceptJson()->withHeaders(['X-API-KEY' => $key])->connectTimeout(3)->timeout(8)->post('https://api.sms.ir/v1/send/verify', ['mobile' => $recipient, 'templateId' => $templateId, 'parameters' => array_map(fn ($name, $value) => ['name' => $name, 'value' => $value], array_keys($parameters), array_values($parameters))]);
        } catch (\Throwable) {
            throw new SmsFailure(false); // May have been accepted: never automatically resend.
        }
        $id = $response->json('data.messageId');
        if ($response->successful() && $response->json('status') === 1 && is_scalar($id) && ctype_digit((string) $id)) {
            return (string) $id;
        }
        // Explicit provider rejection is safe to retry. A malformed success/5xx is uncertain.
        throw new SmsFailure($response->successful() && is_int($response->json('status')) && $response->json('status') !== 1);
    }
    public function queryDelivery(string $messageId): ?string
    {
        if (!ctype_digit($messageId) || !config('identity.sms_ir.api_key')) {
            return null;
        }
        try {
            $response = Http::acceptJson()->withHeaders(['X-API-KEY' => config('identity.sms_ir.api_key')])->connectTimeout(3)->timeout(8)->get('https://api.sms.ir/v1/send/'.$messageId);
            $code = $response->json('data.deliveryState');
            return $response->successful() && is_scalar($code) && ctype_digit((string) $code) ? (string) $code : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
