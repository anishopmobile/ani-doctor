<?php

namespace App\Modules\Bale\Infrastructure;

use App\Modules\Identity\Domain\SmsProviderInterface;
use App\Modules\Booking\Domain\BookingFailure;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class BaleClient implements SmsProviderInterface
{
    public function sendOtp(string $mobile, #[\SensitiveParameter] string $code): void
    {
        $this->enabled();
        try {
            $response = Http::connectTimeout(5)->timeout(15)->withoutRedirecting()
                ->withHeaders(['api-access-key' => config('bale.safir_key')])
                ->post('https://safir.bale.ai/api/v3/send_message', [
                    'request_id' => (string) Str::uuid(), 'bot_id' => (int) config('bale.bot_id'),
                    'phone_number' => '98'.substr($mobile, 1), 'message_data' => ['otp_message' => ['otp' => $code]],
                ]);
            if (!$response->successful() || $response->json('error_data') || !$response->json('message_id')) {
                throw new \RuntimeException();
            }
        } catch (\Throwable) {
            throw new BookingFailure('BALE_UNAVAILABLE', 503);
        }
    }

    public function call(string $method, array $data): mixed
    {
        $this->enabled();
        if (!in_array($method, ['getUpdates', 'sendMessage', 'getFile'], true)) {
            throw new \LogicException('Unsupported Bale method');
        }
        try {
            $response = Http::connectTimeout(5)->timeout(25)->withoutRedirecting()->post('https://tapi.bale.ai/bot'.config('bale.bot_token').'/'.$method, $data);
            if (!$response->successful() || $response->json('ok') !== true) {
                throw new \RuntimeException();
            }
            return $response->json('result');
        } catch (\Throwable) {
            throw new BookingFailure('BALE_UNAVAILABLE', 503);
        }
    }

    private function enabled(): void
    {
        if (!config('bale.enabled') || !config('bale.bot_token')) {
            throw new BookingFailure('BALE_DISABLED', 503);
        }
    }

    public function download(string $fileId): \Illuminate\Http\UploadedFile
    {
        $info = $this->call('getFile', ['file_id' => $fileId]);
        $path = $info['file_path'] ?? '';
        if (!is_string($path) || !preg_match('~\A[a-zA-Z0-9_/-]+\.[a-zA-Z0-9]+\z~', $path) || str_contains($path, '..') || ($info['file_size'] ?? PHP_INT_MAX) > 5 * 1024 * 1024) {
            throw new BookingFailure('INVALID_FILE', 422);
        }
        $temp = tempnam(sys_get_temp_dir(), 'bale-');
        try {
            $response = Http::connectTimeout(5)->timeout(25)->withoutRedirecting()->withOptions([
                'sink' => $temp,
                'progress' => function ($total, $downloaded): void {
                    if ($total > 5242880 || $downloaded > 5242880) {
                        throw new \RuntimeException();
                    }
                },
            ])->get('https://tapi.bale.ai/file/bot'.config('bale.bot_token').'/'.$path);
            if (!$response->successful() || filesize($temp) > 5242880) {
                throw new \RuntimeException();
            }
            return new \Illuminate\Http\UploadedFile($temp, basename($path), null, UPLOAD_ERR_OK, true);
        } catch (\Throwable) {
            @unlink($temp);
            throw new BookingFailure('BALE_UNAVAILABLE', 503);
        }
    }
}
