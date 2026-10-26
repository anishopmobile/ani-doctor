<?php

namespace App\Modules\Billing\Application;

use App\Modules\Booking\Application\BookingService;
use App\Modules\Booking\Application\FinancialBookingService;
use App\Modules\Booking\Domain\BookingFailure;
use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Identity\Domain\BookingActor;
use App\Modules\Billing\Application\FinancialAudit as AuditService;
use Illuminate\Support\Facades\DB;

final class OfflinePaymentService
{
    public function __construct(private readonly FinancialBookingService $booking, private readonly BookingFinance $finance)
    {
    }
    public function emergencySetting(BookingActor $actor, bool $enabled, int $minutes, string $reason): void
    {
        if (!$actor->can('payments.settings')) {
            throw new BookingFailure('FORBIDDEN', 403);
        }
        DB::transaction(function () use ($actor, $enabled, $minutes, $reason): void {
            $before = DB::table('payment_settings')->where('id', 1)->lockForUpdate()->first();
            $values = ['emergency_until' => $enabled ? now()->addMinutes($minutes) : null, 'reason' => $reason, 'updated_by' => $actor->userId, 'updated_at' => now()];
            DB::table('payment_settings')->where('id', 1)->update($values);
            app(AuditService::class)->record('payment.emergency', $actor->userId, 'payment_settings', '1', (array) $before, $values, $reason);
        }, 3);
    }
    public function confirmEmergency(BookingActor $actor, Appointment $snapshot, int $version, string $reason): Appointment
    {
        app(BookingService::class)->authorize($actor, $snapshot);
        return DB::transaction(function () use ($actor, $snapshot, $version, $reason): Appointment {
            // Setting lock precedes booking locks; settings changes never acquire booking locks.
            $settings = DB::table('payment_settings')->where('id', 1)->sharedLock()->first();
            if (!$settings->emergency_until || now()->greaterThanOrEqualTo($settings->emergency_until)) {
                throw new BookingFailure('EMERGENCY_DISABLED');
            }
            $appointment = $this->booking->lock($snapshot);
            app(BookingService::class)->authorize($actor, $appointment);
            if ($appointment->status === 'confirmed' && $appointment->payment_status === 'unpaid') {
                return $appointment;
            }
            if ($appointment->version !== $version || !$this->booking->confirmable($appointment)) {
                throw new BookingFailure('STALE_APPOINTMENT');
            }
            $this->finance->assertEditable($appointment);
            $this->booking->confirm($appointment, 'unpaid', $appointment->price_irr, $actor->userId, $reason, $actor->key);
            return $appointment;
        }, 3);
    }
    public function collect(BookingActor $actor, Appointment $snapshot, int $amount, string $receipt, string $reason, string $method): Appointment
    {
        if (!$actor->can('payments.collect')) {
            throw new BookingFailure('FORBIDDEN', 403);
        }
        return DB::transaction(function () use ($actor, $snapshot, $amount, $receipt, $reason, $method): Appointment {
            $appointment = $this->booking->lock($snapshot);
            $prior = DB::table('payments')->where('provider', 'clinic')->where('provider_reference', $receipt)->lockForUpdate()->first();
            if ($prior) {
                if ((int) $prior->appointment_id !== $appointment->id || (int) $prior->amount_irr !== $amount || (int) $prior->actor_id !== $actor->userId || $prior->reason !== $method.': '.$reason) {
                    throw new BookingFailure('IDEMPOTENCY_CONFLICT');
                }
                return $appointment;
            }
            if ($appointment->status !== 'confirmed' || $appointment->payment_status !== 'unpaid' || $amount !== (int) $appointment->balance_due_irr || $amount <= 0) {
                throw new BookingFailure('INVALID_STATE');
            }
            DB::table('payments')->insert(['appointment_id' => $appointment->id, 'provider' => 'clinic', 'provider_reference' => $receipt, 'amount_irr' => $amount, 'credit_irr' => 0, 'allocated' => true, 'actor_id' => $actor->userId, 'reason' => $method.': '.$reason, 'created_at' => now()]);
            $this->booking->financialStatus($appointment, 'paid', 0);
            app(AuditService::class)->record('payment.clinic', $actor->userId, 'appointment', (string) $appointment->id, ['balance_due_irr' => $amount], ['amount_irr' => $amount, 'method' => $method, 'receipt' => $receipt], $reason);
            return $appointment;
        }, 3);
    }
}
