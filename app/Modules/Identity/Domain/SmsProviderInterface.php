<?php

namespace App\Modules\Identity\Domain;

interface SmsProviderInterface
{
    public function sendOtp(string $mobile, #[\SensitiveParameter] string $code): void;
}
