<?php

namespace App\Modules\Booking\Application;

use App\Modules\Billing\Application\OfflinePaymentService;
use App\Modules\Booking\Domain\BookingFailure;
use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Identity\Domain\BookingActor;
use Illuminate\Support\Facades\DB;

final class DeskBookingService
{
    public function __construct(private readonly BookingService $bookings, private readonly OfflinePaymentService $payments)
    {
    }
    public function create(BookingActor $actor, array $data): Appointment
    {
        if (!$actor->userId || !$actor->staff() || !$actor->can('desk.book')) {
            throw new BookingFailure('FORBIDDEN', 403);
        }
        return DB::transaction(function () use ($actor, $data): Appointment {
            if ($data['settlement'] === 'clinic_due') {
                DB::table('payment_settings')->where('id', 1)->sharedLock()->first();
            }
            $appointment = $this->bookings->create($actor, $data);
            // Replays must never receive money or reconfirm a later-cancelled/completed booking.
            if ($appointment->status !== 'pending_payment' || !$appointment->active()) {
                return $appointment;
            }
            return match ($data['settlement']) {
                'clinic_paid' => $this->payments->confirmCollectedHold($actor, $appointment, (int) $data['received_irr'], $data['receipt'], $data['reason'], $data['method']),
                'clinic_due' => $this->payments->confirmEmergency($actor, $appointment, $appointment->version, $data['reason']),
                'hold' => $appointment,
                default => throw new BookingFailure('INVALID_STATE'),
            };
        }, 3);
    }
}
