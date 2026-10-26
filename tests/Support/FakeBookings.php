<?php

namespace Tests\Support;

use App\Modules\Scheduling\Domain\BookingReadInterface;
use DateTimeImmutable;

final class FakeBookings implements BookingReadInterface
{
    public function __construct(public array $items = [])
    {
    }
    public function between(DateTimeImmutable $fromUtc, DateTimeImmutable $toUtc): array
    {
        return array_values(array_filter($this->items, fn ($item) => new DateTimeImmutable($item['start_at']) < $toUtc && new DateTimeImmutable($item['end_at']) > $fromUtc));
    }
}
