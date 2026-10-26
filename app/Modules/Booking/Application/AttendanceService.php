<?php

namespace App\Modules\Booking\Application;

use App\Modules\Booking\Domain\BookingFailure;
use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Identity\Domain\BookingActor;
use Illuminate\Support\Facades\DB;

final class AttendanceService
{
    public function __construct(private readonly FinancialBookingService $locks)
    {
    }
    public function change(BookingActor $actor, Appointment $snapshot, array $data): Appointment
    {
        if (!$actor->userId || !$actor->staff() || !$actor->can('attendance.manage')) {
            throw new BookingFailure('FORBIDDEN', 403);
        }
        $hash = hash('sha256', json_encode([$snapshot->reference, $data['action'], (int) $data['expected_version'], $data['reason']]));
        return DB::transaction(function () use ($actor, $snapshot, $data, $hash): Appointment {
            $appointment = $this->locks->lock($snapshot);
            $existing = DB::table('attendance_events')->where('actor_hash', $actor->key)->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($existing) {
                if (!hash_equals($existing->payload_hash, $hash)) {
                    throw new BookingFailure('IDEMPOTENCY_CONFLICT');
                }
                return $appointment;
            }
            if ($appointment->version !== (int) $data['expected_version']) {
                throw new BookingFailure('STALE_APPOINTMENT');
            }
            if (!in_array($data['action'], $this->actions($appointment), true)) {
                throw new BookingFailure('ATTENDANCE_INVALID_STATE');
            }
            $before = $this->state($appointment);
            switch ($data['action']) {
                case 'arrived':
                    $appointment->forceFill(['attendance_status' => 'arrived', 'arrived_at' => now()]);
                    break;
                case 'scheduled':
                    $appointment->forceFill(['attendance_status' => 'scheduled', 'arrived_at' => null]);
                    break;
                case 'completed':
                    $appointment->forceFill(['status' => 'completed', 'attendance_status' => 'completed', 'completed_at' => now()]);
                    break;
                case 'no_show':
                    $appointment->forceFill(['status' => 'no_show', 'attendance_status' => 'no_show', 'no_show_at' => now()]);
                    break;
                default:
                    throw new BookingFailure('ATTENDANCE_INVALID_STATE');
            }
            $appointment->version++;
            $appointment->save();
            DB::table('attendance_events')->insert(['appointment_id' => $appointment->id, 'actor_id' => $actor->userId, 'actor_hash' => $actor->key, 'idempotency_key' => $data['idempotency_key'], 'payload_hash' => $hash, 'action' => $data['action'], 'before_state' => json_encode($before), 'after_state' => json_encode($this->state($appointment)), 'reason' => $data['reason'], 'created_at' => now()]);
            return $appointment;
        }, 3);
    }
    public function actions(Appointment $appointment): array
    {
        if ($appointment->status !== 'confirmed') {
            return [];
        }
        $sameDay = $appointment->local_date === now('Asia/Tehran')->toDateString();
        if ($appointment->attendance_status === 'arrived') {
            return [...($sameDay ? ['scheduled'] : []), ...(!$appointment->start_at->isFuture() ? ['completed'] : [])];
        }
        if ($appointment->attendance_status === 'scheduled') {
            return [...($sameDay ? ['arrived'] : []), ...(!$appointment->occupied_until->isFuture() ? ['no_show'] : [])];
        }
        return [];
    }
    private function state(Appointment $appointment): array
    {
        return ['status' => $appointment->status, 'attendance_status' => $appointment->attendance_status, 'version' => $appointment->version, 'arrived_at' => $appointment->arrived_at?->toIso8601String(), 'completed_at' => $appointment->completed_at?->toIso8601String(), 'no_show_at' => $appointment->no_show_at?->toIso8601String()];
    }
}
