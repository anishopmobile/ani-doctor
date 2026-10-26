<?php

namespace App\Modules\Booking\Application;

use App\Modules\Booking\Domain\BookingFailure;
use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Catalog\Infrastructure\Service;
use App\Modules\Scheduling\Application\AvailabilityService;
use Illuminate\Support\Facades\DB;

final class FinancialBookingService
{
    public function __construct(private readonly BookingLocks $locks, private readonly AvailabilityService $availability)
    {
    }
    public function lock(Appointment $snapshot): Appointment
    {
        $this->locks->configuration();
        Service::withTrashed()->whereKey($snapshot->service_id)->sharedLock()->first();
        $this->locks->actors([$snapshot->booker_mobile_hash]);
        DB::table('patients')->where('id', $snapshot->patient_id)->lockForUpdate()->first();
        $this->locks->days([$snapshot->local_date]);
        $appointment = Appointment::whereKey($snapshot->id)->lockForUpdate()->firstOrFail();
        if ($appointment->local_date !== $snapshot->local_date) {
            throw new BookingFailure('STALE_APPOINTMENT');
        }
        return $appointment;
    }
    public function confirmable(Appointment $appointment): bool
    {
        if ($appointment->status !== 'pending_payment' || !$appointment->hold_expires_at?->isFuture()) {
            return false;
        }
        $service = Service::find($appointment->service_id);
        if (!$service || !$service->is_active) {
            return false;
        }
        $service->forceFill(['duration_minutes' => $appointment->duration_minutes, 'buffer_minutes' => $appointment->buffer_minutes, 'capacity' => $appointment->capacity_limit]);
        $slot = $this->availability->candidate($service, $appointment->start_at, $appointment->reference, $appointment->channel === 'urgent');
        return $slot && ($slot['is_bookable'] || ($appointment->is_override && $appointment->start_at->isFuture()));
    }
    public function confirm(Appointment $appointment, string $paymentStatus, int $due, ?int $actorId, string $reason, ?string $actorHash = null): void
    {
        $before = $appointment->status;
        $appointment->forceFill(['status' => 'confirmed', 'payment_status' => $paymentStatus, 'balance_due_irr' => $due, 'version' => $appointment->version + 1])->save();
        $this->event($appointment, $before, 'confirmed', $actorId, $reason, $actorHash);
        app(\App\Modules\Notifications\Application\NotificationOutbox::class)->record($appointment, 'confirmed');
    }
    public function financialStatus(Appointment $appointment, string $status, int $due): void
    {
        $appointment->forceFill(['payment_status' => $status, 'balance_due_irr' => $due])->save();
    }
    private function event(Appointment $appointment, string $before, string $action, ?int $actorId, string $reason, ?string $actorHash): void
    {
        DB::table('appointment_events')->insert(['appointment_id' => $appointment->id, 'actor_id' => $actorId, 'actor_hash' => $actorHash ?? hash('sha256', 'financial-settlement'), 'action' => $action, 'reason' => $reason, 'before_state' => json_encode(['status' => $before]), 'after_state' => json_encode(['status' => $appointment->status, 'payment_status' => $appointment->payment_status, 'version' => $appointment->version]), 'created_at' => now()]);
    }
}
