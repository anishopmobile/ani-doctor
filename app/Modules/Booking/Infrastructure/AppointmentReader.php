<?php

namespace App\Modules\Booking\Infrastructure;

use App\Modules\Scheduling\Domain\BookingReadInterface;
use DateTimeImmutable;

final class AppointmentReader implements BookingReadInterface
{
    public function between(DateTimeImmutable $fromUtc, DateTimeImmutable $toUtc): array
    {
        $query = Appointment::where('start_at', '<', $toUtc)->where('occupied_until', '>', $fromUtc)->whereIn('status', ['confirmed','pending_payment', 'completed', 'no_show']);
        // Locking reads avoid an old REPEATABLE READ snapshot after waiting for a day lock.
        if (\Illuminate\Support\Facades\DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }
        return $query->get()->map(fn ($item) => [
                'reference' => $item->reference,'start_at' => $item->start_at->toIso8601String(),'end_at' => $item->occupied_until->toIso8601String(),
                'status' => $item->status,'hold_expires_at' => $item->hold_expires_at?->toIso8601String(),'capacity_limit' => $item->capacity_limit,
            ])->all();
    }
}
