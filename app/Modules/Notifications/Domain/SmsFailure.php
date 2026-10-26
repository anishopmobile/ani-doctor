<?php

namespace App\Modules\Notifications\Domain;

final class SmsFailure extends \RuntimeException
{
    public function __construct(public readonly bool $retryable)
    {
        parent::__construct($retryable ? 'SMS_REJECTED' : 'SMS_UNCERTAIN');
    }
}
