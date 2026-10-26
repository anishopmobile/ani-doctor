<?php

namespace App\Modules\Scheduling\Domain;

use DateTimeImmutable;

interface BookingReadInterface
{
    /**
     * Read-only integration point for P3; no patient identifiers or names.
     * @return array<array{start_at: string, end_at: string, status: string, hold_expires_at?: ?string, capacity_limit?: int}>
     */
    public function between(DateTimeImmutable $fromUtc, DateTimeImmutable $toUtc): array;
}
