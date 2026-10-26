<?php

namespace App\Modules\Booking\Application;

use App\Modules\Identity\Domain\BookingActor;
use App\Modules\Booking\Infrastructure\Appointment;

final class AppointmentAccess
{
    public function query(BookingActor $actor)
    {
        $query = Appointment::query();
        if (!$actor->staff()) {
            $query->where(function ($query) use ($actor): void {
                if ($actor->userId) {
                    $query->where('booker_user_id', $actor->userId)->orWhere(fn ($guest) => $guest->whereNull('booker_user_id')->where('booker_mobile_hash', $actor->mobileHash));
                } else {
                    $query->whereNull('booker_user_id')->where('booker_mobile_hash', $actor->mobileHash);
                }
            });
        }
        return $query;
    }
}
