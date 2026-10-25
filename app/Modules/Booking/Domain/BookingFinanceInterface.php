<?php

namespace App\Modules\Booking\Domain;

use App\Modules\Booking\Infrastructure\Appointment;

interface BookingFinanceInterface
{
    public function cancel(Appointment $appointment): void;
    public function expire(Appointment $appointment): void;
    public function assertEditable(Appointment $appointment): void;
}
