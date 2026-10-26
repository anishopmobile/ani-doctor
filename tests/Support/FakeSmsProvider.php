<?php

namespace Tests\Support;

use App\Modules\Identity\Domain\SmsProviderInterface;

final class FakeSmsProvider implements SmsProviderInterface
{
    public array $messages = [];

    public bool $fail = false;

    public function sendOtp(string $mobile, #[\SensitiveParameter] string $code): void
    {
        if ($this->fail) {
            throw new \RuntimeException('Provider body contains secrets and must never escape');
        }
        $this->messages[] = ['mobile' => $mobile, 'code' => $code];
    }

    public function lastCode(): string
    {
        return $this->messages[array_key_last($this->messages)]['code'];
    }
}
