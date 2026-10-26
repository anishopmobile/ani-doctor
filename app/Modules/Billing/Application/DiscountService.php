<?php

namespace App\Modules\Billing\Application;

use App\Modules\Booking\Domain\BookingFailure;
use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Identity\Infrastructure\User;
use App\Modules\SettingsAudit\Application\AuditService;
use Illuminate\Support\Facades\DB;

final class DiscountService
{
    public function create(User $actor, array $data): int
    {
        abort_unless($actor->hasPermission('discounts.manage'), 403);
        return DB::transaction(function () use ($actor, $data): int {
            $values = array_intersect_key($data, array_flip(['code', 'kind', 'value', 'max_discount_irr', 'max_uses', 'per_patient', 'starts_at', 'ends_at']));
            $values['service_ids'] = json_encode(array_values(array_map('intval', $data['service_ids'])));
            $id = DB::table('discounts')->insertGetId([...$values, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            app(AuditService::class)->record($actor->id, 'discount.created', null, ['id' => $id, ...$values, 'reason' => $data['reason']]);
            return $id;
        }, 3);
    }
    public function toggle(User $actor, int $id, int $version, bool $active, string $reason): void
    {
        abort_unless($actor->hasPermission('discounts.manage'), 403);
        DB::transaction(function () use ($actor, $id, $version, $active, $reason): void {
            $offer = DB::table('discounts')->where('id', $id)->lockForUpdate()->first();
            abort_unless($offer, 404);
            abort_unless($offer->version === $version, 409, 'تخفیف تغییر کرده است؛ صفحه را تازه کنید.');
            DB::table('discounts')->where('id', $id)->update(['is_active' => $active, 'version' => $version + 1, 'updated_at' => now()]);
            app(AuditService::class)->record($actor->id, 'discount.state', ['id' => $id, 'active' => $offer->is_active], ['id' => $id, 'active' => $active, 'reason' => $reason]);
        }, 3);
    }
    /** Caller owns patient/day locks. The offer lock serializes quota across all days/patients. */
    public function quote(?string $code, int $service, int $patient, int $base): array
    {
        if (!$code) {
            return ['base' => $base, 'discount' => 0, 'net' => $base, 'offer' => null];
        }
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('DISCOUNT_REQUIRES_TRANSACTION');
        }
        $offer = DB::table('discounts')->where('code', strtoupper(trim($code)))->lockForUpdate()->first();
        if (!$offer || !$offer->is_active || now()->lt($offer->starts_at) || now()->greaterThanOrEqualTo($offer->ends_at) || !in_array($service, json_decode($offer->service_ids, true), true) || $base <= 0) {
            throw new BookingFailure('DISCOUNT_UNAVAILABLE');
        }
        // Current read of redemption rows only: never acquire another appointment under this lock.
        $used = DB::table('discount_redemptions')->where('discount_id', $offer->id)->where(fn ($q) => $q->where('state', 'consumed')->orWhere(fn ($r) => $r->where('state', 'reserved')->where('reserved_until', '>', now())))->lockForUpdate()->get(['patient_id']);
        if ($used->count() >= $offer->max_uses || $used->where('patient_id', $patient)->count() >= $offer->per_patient) {
            throw new BookingFailure('DISCOUNT_UNAVAILABLE');
        }
        $amount = $offer->kind === 'percent' ? intdiv($base * $offer->value, 100) : $offer->value;
        $amount = min($base, $amount, $offer->max_discount_irr ?? $base);
        return ['base' => $base, 'discount' => $amount, 'net' => $base - $amount, 'offer' => $offer];
    }
    public function reserve(Appointment $appointment, array $quote): void
    {
        if (!$quote['offer']) {
            return;
        }
        $id = DB::table('discount_redemptions')->insertGetId(['discount_id' => $quote['offer']->id, 'appointment_id' => $appointment->id, 'patient_id' => $appointment->patient_id, 'base_irr' => $quote['base'], 'discount_irr' => $quote['discount'], 'offer_version' => $quote['offer']->version, 'reserved_until' => $appointment->hold_expires_at, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('discount_events')->insert(['redemption_id' => $id, 'action' => 'reserved', 'created_at' => now()]);
    }
    public function transition(Appointment $appointment, string $target): void
    {
        $snapshot = DB::table('discount_redemptions')->where('appointment_id', $appointment->id)->first();
        if (!$snapshot) {
            return;
        }
        DB::table('discounts')->where('id', $snapshot->discount_id)->lockForUpdate()->first();
        $row = DB::table('discount_redemptions')->where('id', $snapshot->id)->lockForUpdate()->first();
        if ($row->state !== 'reserved') {
            return; // Consumed discounts stay consumed after cancellation; do not recycle usage.
        }
        if ($target === 'consumed' && now()->greaterThanOrEqualTo($row->reserved_until)) {
            throw new BookingFailure('HOLD_EXPIRED');
        }
        DB::table('discount_redemptions')->where('id', $row->id)->update(['state' => $target, 'updated_at' => now()]);
        DB::table('discount_events')->insert(['redemption_id' => $row->id, 'action' => $target, 'created_at' => now()]);
    }
}
