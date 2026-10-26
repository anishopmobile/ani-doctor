<?php

namespace App\Modules\Booking\Application;

use App\Modules\Identity\Domain\BookingActor;
use App\Modules\Booking\Domain\BookingFailure;
use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Catalog\Infrastructure\Service;
use App\Modules\Patients\Application\BookingPatientService;
use App\Modules\Scheduling\Application\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BookingService
{
    public function __construct(private readonly BookingLocks $locks, private readonly AvailabilityService $availability, private readonly BookingPatientService $patients, private readonly \App\Modules\Booking\Domain\BookingFinanceInterface $finance)
    {
    }

    public function create(BookingActor $actor, array $data): Appointment
    {
        $ownerHash = $actor->staff() ? hash_hmac('sha256', $data['booker_mobile'], (string)config('app.key')) : $actor->mobileHash;
        $start = CarbonImmutable::parse($data['start_at'])->utc();
        $date = $start->setTimezone('Asia/Tehran')->toDateString();
        return DB::transaction(function () use ($actor, $data, $ownerHash, $start, $date): Appointment {
            $settings = $this->locks->configuration();
            $service = Service::whereKey($data['service_id'])->sharedLock()->first();
            $this->locks->actors([$actor->key,$ownerHash]);
            if ($existing = $this->replay($actor, $data, 'create')) {
                return $existing;
            }
            if (!$service || !$service->is_active || (!$actor->staff() && !$service->is_online)) {
                throw new BookingFailure('SERVICE_UNAVAILABLE', 404);
            }
            if ((int)$data['expected_price_irr'] !== $service->price_irr) {
                throw new BookingFailure('PRICE_CHANGED');
            }
            $patient = $this->patients->resolve($actor, $data, $ownerHash);
            // The patient row serializes limits even when different operators book different days.
            DB::table('patients')->where('id', $patient->id)->lockForUpdate()->first();
            $this->locks->days([$date]);
            $slot = $this->slot($actor, $service, $start, $data);
            if ($this->active()->where('patient_id', $patient->id)->lockForUpdate()->get(['id'])->count() >= $settings->max_active_patient
                || $this->active()->where('booker_mobile_hash', $ownerHash)->lockForUpdate()->get(['id'])->count() >= $settings->max_active_booker) {
                throw new BookingFailure('BOOKING_LIMIT_REACHED');
            }
            $appointment = Appointment::create([
                'reference' => (string)Str::uuid(),'patient_id' => $patient->id,
                'booker_user_id' => $actor->staff() ? null : $actor->userId,'booker_mobile_hash' => $ownerHash,
                'service_id' => $service->id,'service_title' => $service->name,'price_irr' => $service->price_irr,
                'duration_minutes' => $service->duration_minutes,'buffer_minutes' => $service->buffer_minutes,
                'capacity_limit' => $slot['capacity_limit'],'local_date' => $date,'start_at' => $start,
                'end_at' => $start->addMinutes($service->duration_minutes),'occupied_until' => $start->addMinutes($service->duration_minutes + $service->buffer_minutes),
                'status' => 'pending_payment','payment_status' => 'unpaid','balance_due_irr' => $service->price_irr,'hold_expires_at' => now()->addMinutes($settings->hold_minutes),
                'channel' => $actor->staff() ? 'secretary' : ($actor->userId ? 'user' : 'guest'),
                'is_override' => !empty($data['override_capacity']),'override_reason' => $data['reason'] ?? null,'created_by' => $actor->userId,'version' => 1,
            ]);
            $this->event($appointment, $actor, 'held', null, $data['reason'] ?? null);
            $this->remember($actor, $data, 'create', $appointment);
            return $appointment;
        }, 3);
    }

    public function change(BookingActor $actor, Appointment $snapshot, array $data, string $action): Appointment
    {
        $data['appointment_reference'] = $snapshot->reference;
        $target = $action === 'reschedule' ? CarbonImmutable::parse($data['start_at'])->utc() : null;
        $dates = [$snapshot->local_date];
        if ($target) {
            $dates[] = $target->setTimezone('Asia/Tehran')->toDateString();
        }
        return DB::transaction(function () use ($actor, $snapshot, $data, $action, $target, $dates): Appointment {
            $settings = $this->locks->configuration();
            $service = $action === 'reschedule' ? Service::whereKey($snapshot->service_id)->sharedLock()->first() : null;
            $this->locks->actors([$actor->key,$snapshot->booker_mobile_hash]);
            if ($existing = $this->replay($actor, $data, $action)) {
                return $existing;
            }
            DB::table('patients')->where('id', $snapshot->patient_id)->lockForUpdate()->first();
            $this->locks->days($dates);
            $appointment = Appointment::whereKey($snapshot->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $appointment);
            if ($appointment->version !== (int)$data['expected_version'] || $appointment->version !== $snapshot->version || $appointment->local_date !== $snapshot->local_date) {
                throw new BookingFailure('STALE_APPOINTMENT');
            }
            $before = $this->state($appointment);
            if ($action === 'claim') {
                if (!$actor->userId || $appointment->booker_user_id !== null || !hash_equals($actor->mobileHash, $appointment->booker_mobile_hash)) {
                    throw new BookingFailure('FORBIDDEN', 403);
                }
                $appointment->booker_user_id = $actor->userId;
                $this->patients->claim($appointment->patient_id, $actor->userId);
            } else {
                if (!$appointment->active()) {
                    throw new BookingFailure($appointment->effectiveStatus() === 'expired' ? 'HOLD_EXPIRED' : 'INVALID_STATE');
                }
                if ($appointment->start_at->timestamp - now()->timestamp < $settings->cutoff_hours * 3600) {
                    if (!$actor->can('appointments.override_window') || empty($data['reason'])) {
                        throw new BookingFailure('EDIT_WINDOW_CLOSED');
                    }
                }
                if ($action === 'cancel') {
                    $this->finance->cancel($appointment);
                    $appointment->status = 'cancelled';
                } elseif ($action === 'reschedule') {
                    $this->finance->assertEditable($appointment);
                    if (!$service || !$service->is_active || (!$actor->staff() && !$service->is_online)) {
                        throw new BookingFailure('SERVICE_UNAVAILABLE');
                    }
                    $service->forceFill(['duration_minutes' => $appointment->duration_minutes,'buffer_minutes' => $appointment->buffer_minutes,'capacity' => $appointment->capacity_limit]);
                    $slot = $this->slot($actor, $service, $target, $data, $appointment->reference);
                    $appointment->forceFill([
                        'local_date' => $target->setTimezone('Asia/Tehran')->toDateString(),'start_at' => $target,
                        'capacity_limit' => $slot['capacity_limit'],
                        'end_at' => $target->addMinutes($appointment->duration_minutes),
                        'occupied_until' => $target->addMinutes($appointment->duration_minutes + $appointment->buffer_minutes),
                        'is_override' => !empty($data['override_capacity']),'override_reason' => $data['reason'] ?? null,
                    ]);
                    // Never extend the original payment deadline by rescheduling.
                } else {
                    throw new BookingFailure('INVALID_STATE');
                }
            }
            $appointment->version++;
            $appointment->save();
            $this->event($appointment, $actor, $action, $before, $data['reason'] ?? null);
            $this->remember($actor, $data, $action, $appointment);
            return $appointment;
        }, 3);
    }

    public function expire(int $id): void
    {
        $snapshot = Appointment::find($id);
        if (!$snapshot) {
            return;
        }
        DB::transaction(function () use ($snapshot): void {
            $this->locks->configuration();
            $this->locks->days([$snapshot->local_date]);
            $appointment = Appointment::whereKey($snapshot->id)->lockForUpdate()->first();
            if ($appointment->local_date !== $snapshot->local_date) {
                return;
            }
            if ($appointment->status !== 'pending_payment' || $appointment->hold_expires_at->isFuture()) {
                return;
            }
            $before = $this->state($appointment);
            $this->finance->expire($appointment);
            $appointment->status = 'expired';
            $appointment->version++;
            $appointment->save();
            DB::table('appointment_events')->insert(['appointment_id' => $appointment->id,'actor_hash' => hash('sha256', 'scheduler'),'action' => 'expired','before_state' => json_encode($before),'after_state' => json_encode($this->state($appointment)),'created_at' => now()]);
        }, 3);
    }

    public function authorize(BookingActor $actor, Appointment $appointment): void
    {
        if (!$actor->owns($appointment->booker_user_id, $appointment->booker_mobile_hash)) {
            throw new BookingFailure('FORBIDDEN', 404);
        }
    }

    private function active()
    {
        return Appointment::where(function ($query): void {
            $query->where('status', 'confirmed')->orWhere(fn ($pending) => $pending->where('status', 'pending_payment')->where('hold_expires_at', '>', now()));
        });
    }

    private function slot(BookingActor $actor, Service $service, CarbonImmutable $start, array $data, ?string $exclude = null): array
    {
        $override = !empty($data['override_capacity']);
        if ($override && (!$actor->can('appointments.override_capacity') || empty($data['reason']))) {
            throw new BookingFailure('FORBIDDEN', 403);
        }
        $date = $start->setTimezone('Asia/Tehran')->startOfDay();
        $day = $this->availability->days($service, $date, $date, $exclude)[0];
        foreach ($day['slots'] as $slot) {
            if (CarbonImmutable::parse($slot['start_at_utc'])->timestamp === $start->timestamp && ($slot['is_bookable'] || ($override && $start->isFuture()))) {
                return ['capacity_limit' => $slot['capacity_limit']];
            }
        }
        throw new BookingFailure('SLOT_UNAVAILABLE');
    }

    private function fingerprint(array $data, string $action): string
    {
        unset($data['idempotency_key']);
        ksort($data);
        return hash_hmac('sha256', json_encode([$action,$data], JSON_THROW_ON_ERROR), (string)config('app.key'));
    }

    private function replay(BookingActor $actor, array $data, string $action): ?Appointment
    {
        $operation = DB::table('booking_operations')->where('actor_hash', $actor->key)->where('key', $data['idempotency_key'])->first();
        if (!$operation) {
            return null;
        }
        if (!hash_equals($operation->payload_hash, $this->fingerprint($data, $action))) {
            throw new BookingFailure('IDEMPOTENCY_CONFLICT');
        }
        $appointment = Appointment::findOrFail($operation->appointment_id);
        $this->authorize($actor, $appointment);
        return $appointment;
    }

    private function remember(BookingActor $actor, array $data, string $action, Appointment $appointment): void
    {
        DB::table('booking_operations')->insert(['actor_hash' => $actor->key,'key' => $data['idempotency_key'],'payload_hash' => $this->fingerprint($data, $action),'appointment_id' => $appointment->id,'created_at' => now()]);
    }

    private function state(Appointment $appointment): array
    {
        return ['status' => $appointment->status,'version' => $appointment->version,'start_at' => $appointment->start_at->toIso8601String(),'end_at' => $appointment->end_at->toIso8601String(),'is_override' => $appointment->is_override];
    }

    private function event(Appointment $appointment, BookingActor $actor, string $action, ?array $before, ?string $reason): void
    {
        DB::table('appointment_events')->insert(['appointment_id' => $appointment->id,'actor_id' => $actor->userId,'actor_hash' => $actor->key,'action' => $action,'reason' => $reason,'before_state' => json_encode($before),'after_state' => json_encode($this->state($appointment)),'created_at' => now()]);
    }
}
