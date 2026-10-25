<?php

namespace App\Modules\Scheduling\Infrastructure;

use App\Modules\Scheduling\Domain\BookingReadInterface;
use DateTimeImmutable;

final class NoBookingsYet implements BookingReadInterface
{
    public function between(DateTimeImmutable $fromUtc, DateTimeImmutable $toUtc): array
    {
        // P2 cannot create bookings. P3 must replace this binding with its read adapter.
        return [];
    }
}
