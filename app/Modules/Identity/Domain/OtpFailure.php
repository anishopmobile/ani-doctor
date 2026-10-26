<?php

namespace App\Modules\Identity\Domain;

final class OtpFailure extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, public readonly int $status = 422)
    {
        parent::__construct($errorCode);
    }
}
