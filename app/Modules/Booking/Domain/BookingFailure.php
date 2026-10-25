<?php

namespace App\Modules\Booking\Domain;

final class BookingFailure extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, public readonly int $status = 409)
    {
        parent::__construct($errorCode);
    }
}
