<?php

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\MobileNumber;
use App\Modules\Identity\Infrastructure\OtpChallenge;
use Illuminate\Support\Facades\Crypt;

final class PrivateOtpDelivery
{
    public function enabled(string $mobile): bool
    {
        $until = strtotime((string) config('identity.private_otp.until'));
        return config('identity.private_otp.enabled') === true
            && MobileNumber::isValid($mobile)
            && hash_equals((string) config('identity.private_otp.mobile'), $mobile)
            && $until !== false && $until > time() && $until <= time() + 86400
            && parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https';
    }

    private function directory(): string
    {
        $path = (string) config('identity.private_otp.directory');
        $real = realpath($path);
        $public = realpath(public_path());
        $document = realpath((string) request()->server('DOCUMENT_ROOT', public_path()));
        if (!$real || is_link($path) || !is_dir($real)) {
            throw new \RuntimeException('PRIVATE_OTP_PATH');
        }
        foreach (array_filter([$public, $document]) as $root) {
            $root = str_replace('\\', '/', $root);
            $candidate = str_replace('\\', '/', $real);
            if (strtolower($candidate) === strtolower($root) || str_starts_with(strtolower($candidate).'/', strtolower($root).'/')) {
                throw new \RuntimeException('PRIVATE_OTP_PATH');
            }
        }
        if (PHP_OS_FAMILY !== 'Windows' && ((fileperms($real) & 0077) !== 0)) {
            throw new \RuntimeException('PRIVATE_OTP_PERMISSIONS');
        }
        return $real;
    }

    public function deliver(string $mobile, string $id, #[\SensitiveParameter] string $code): void
    {
        if (!$this->enabled($mobile)) {
            throw new \RuntimeException('PRIVATE_OTP_DISABLED');
        }
        $dir = $this->directory();
        $this->write($dir.'/pending.json', Crypt::encryptString(json_encode(['mobile' => $mobile, 'id' => $id, 'code' => $code], JSON_THROW_ON_ERROR)));
    }

    private function write(string $path, #[\SensitiveParameter] string $content): void
    {
        if (is_link($path)) {
            throw new \RuntimeException('PRIVATE_OTP_PATH');
        }
        $mask = umask(0077);
        $temp = tempnam(dirname($path), '.otp-');
        try {
            if (!$temp || !chmod($temp, 0600) || file_put_contents($temp, $content, LOCK_EX) === false || !rename($temp, $path)) {
                throw new \RuntimeException('PRIVATE_OTP_WRITE');
            }
        } finally {
            if ($temp && file_exists($temp)) { unlink($temp); }
            umask($mask);
        }
    }

    public function export(): void
    {
        $dir = $this->directory();
        $inbox = $dir.'/login-code.txt';
        $pending = $dir.'/pending.json';
        foreach ([$inbox, $pending] as $path) {
            if (is_link($path)) { throw new \RuntimeException('PRIVATE_OTP_PATH'); }
        }
        // Revalidate on every run; never print the secret to cron output.
        if (file_exists($inbox)) { unlink($inbox); }
        if (!file_exists($pending)) { return; }
        $data = json_decode(Crypt::decryptString(file_get_contents($pending)), true, flags: JSON_THROW_ON_ERROR);
        $challenge = OtpChallenge::whereKey($data['mobile'])->first();
        if (!$this->enabled($data['mobile']) || !$challenge || $challenge->challenge_id !== $data['id']
            || $challenge->status !== 'sent' || !$challenge->expires_at?->isFuture()
            || $challenge->attempts >= config('identity.max_attempts')) {
            unlink($pending);
            return;
        }
        $this->write($inbox, "Code: ".$data['code']."\nExpires: ".$challenge->expires_at->toIso8601String()."\n");
    }
}
