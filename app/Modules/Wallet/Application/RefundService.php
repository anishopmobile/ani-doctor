<?php

namespace App\Modules\Wallet\Application;

use App\Modules\Booking\Domain\BookingFailure;
use App\Modules\Identity\Domain\BookingActor;
use App\Modules\Billing\Application\FinancialAudit as AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RefundService
{
    public function __construct(private readonly WalletService $wallet)
    {
    }
    public function request(BookingActor $actor, int $amount, string $key, string $reason): object
    {
        return DB::transaction(function () use ($actor, $amount, $key, $reason): object {
            $this->wallet->lock($actor->mobileHash);
            $existing = DB::table('refunds')->where('actor_hash', $actor->key)->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->amount_irr !== $amount || $existing->reason !== $reason) {
                    throw new BookingFailure('IDEMPOTENCY_CONFLICT');
                }
                return $existing;
            }
            if ($amount <= 0) {
                throw new BookingFailure('INSUFFICIENT_CREDIT');
            }
            $id = DB::table('refunds')->insertGetId(['reference' => (string) Str::uuid(), 'owner_hash' => $actor->mobileHash, 'actor_hash' => $actor->key, 'idempotency_key' => $key, 'amount_irr' => $amount, 'reason' => $reason, 'created_at' => now(), 'updated_at' => now()]);
            $this->wallet->entry($actor->mobileHash, -$amount, 'refund:'.$id, 'refund_reserved', null, $id);
            app(AuditService::class)->record('refund.request', $actor->userId, 'refund', (string) $id, [], ['amount_irr' => $amount], $reason);
            return DB::table('refunds')->where('id', $id)->first();
        }, 3);
    }
    public function decide(BookingActor $actor, string $reference, string $decision, string $reason, ?string $bankReference): object
    {
        $bankReference = $decision === 'completed' ? $bankReference : null;
        if (!$actor->can('refunds.manage')) {
            throw new BookingFailure('FORBIDDEN', 403);
        }
        $snapshot = DB::table('refunds')->where('reference', $reference)->firstOrFail();
        return DB::transaction(function () use ($actor, $snapshot, $decision, $reason, $bankReference): object {
            $this->wallet->lock($snapshot->owner_hash);
            $refund = DB::table('refunds')->where('id', $snapshot->id)->lockForUpdate()->first();
            if ($refund->status === $decision) {
                if ($refund->decision_reason !== $reason || $refund->bank_reference !== $bankReference) {
                    throw new BookingFailure('IDEMPOTENCY_CONFLICT');
                }
                return $refund;
            }
            if (!in_array($decision, ['processing', 'rejected', 'completed'], true) || !in_array($refund->status, ['requested', 'processing'], true) || ($decision === 'completed' && ($refund->status !== 'processing' || !$bankReference))) {
                throw new BookingFailure('INVALID_STATE');
            }
            if ($decision === 'rejected') {
                $this->wallet->entry($refund->owner_hash, $refund->amount_irr, 'refund-rejected:'.$refund->id, 'refund_reversed', null, $refund->id);
            }
            $changes = ['status' => $decision, 'decision_reason' => $reason, 'bank_reference' => $decision === 'completed' ? $bankReference : null, 'decided_by' => $actor->userId, 'updated_at' => now()];
            DB::table('refunds')->where('id', $refund->id)->update($changes);
            app(AuditService::class)->record('refund.'.$decision, $actor->userId, 'refund', (string) $refund->id, ['status' => $refund->status], ['status' => $decision, 'amount_irr' => $refund->amount_irr, 'bank_reference' => $changes['bank_reference']], $reason);
            return DB::table('refunds')->where('id', $refund->id)->first();
        }, 3);
    }
}
