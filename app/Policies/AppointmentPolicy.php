<?php

namespace App\Policies;

use App\Modules\Booking\Application\ActorResolver;
use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Identity\Infrastructure\User;

final class AppointmentPolicy
{
    public function __construct(private readonly ActorResolver $actors)
    {
    }
    public function view(?User $user, Appointment $appointment): bool
    {
        $actor = $this->actors->resolve(request());
        return $actor->owns($appointment->booker_user_id, $appointment->booker_mobile_hash);
    }
}
