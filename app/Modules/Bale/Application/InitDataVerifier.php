<?php

namespace App\Modules\Bale\Application;

use App\Modules\Booking\Domain\BookingFailure;

final class InitDataVerifier
{
    public function verify(#[\SensitiveParameter] string $raw): string
    {
        if (!config('bale.enabled') || !config('bale.bot_token') || strlen($raw) > 8192) {
            throw new BookingFailure('BALE_DISABLED', 503);
        }
        $data = [];
        foreach (explode('&', $raw) as $pair) {
            $parts = explode('=', $pair, 2);
            $key = urldecode($parts[0]);
            if (count($parts) !== 2 || !preg_match('/\A[a-z_]+\z/', $key) || array_key_exists($key, $data)) {
                throw new BookingFailure('FORBIDDEN', 401);
            }
            $data[$key] = urldecode($parts[1]);
        }
        $signature = $data['hash'] ?? '';
        unset($data['hash']);
        ksort($data, SORT_STRING);
        $check = implode("\n", array_map(fn ($key) => $key.'='.$data[$key], array_keys($data)));
        $key = hash_hmac('sha256', (string) config('bale.bot_token'), 'WebAppData', true);
        if (!hash_equals(hash_hmac('sha256', $check, $key), $signature)
            || !ctype_digit($data['auth_date'] ?? '') || (int) $data['auth_date'] > now()->timestamp + 30
            || now()->timestamp - (int) $data['auth_date'] > config('bale.init_ttl')) {
            throw new BookingFailure('FORBIDDEN', 401);
        }
        $user = json_decode($data['user'] ?? '', true);
        $id = $user['id'] ?? null;
        if ((!is_int($id) && !is_string($id)) || !preg_match('/\A[1-9][0-9]{0,18}\z/', (string) $id)) {
            throw new BookingFailure('FORBIDDEN', 401);
        }
        return (string) $id;
    }
}
