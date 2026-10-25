<?php

namespace App\Modules\Wallet\Application;

use App\Modules\Booking\Domain\BookingFailure;
use Illuminate\Support\Facades\DB;

final class WalletService
{
    // All writes belong to the caller's transaction; balances are projections of immutable entries.
    public function lock(string $owner): object
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Wallet writes require a transaction.');
        }
        DB::table('wallet_accounts')->insertOrIgnore(['owner_hash' => $owner]);
        return DB::table('wallet_accounts')->where('owner_hash', $owner)->lockForUpdate()->first();
    }
    public function reserve(string $owner, int $amount): void
    {
        $wallet = $this->lock($owner);
        if ($amount < 0 || $amount > $wallet->balance_irr - $wallet->reserved_irr) {
            throw new BookingFailure('INSUFFICIENT_CREDIT');
        }
        DB::table('wallet_accounts')->where('owner_hash', $owner)->increment('reserved_irr', $amount);
    }
    public function release(string $owner, int $amount): void
    {
        $wallet = $this->lock($owner);
        if ($amount < 0 || $amount > $wallet->reserved_irr) {
            throw new \LogicException('Invalid credit reservation.');
        }
        DB::table('wallet_accounts')->where('owner_hash', $owner)->decrement('reserved_irr', $amount);
    }
    public function entry(string $owner, int $amount, string $key, string $kind, ?int $appointment = null, ?int $refund = null): void
    {
        $wallet = $this->lock($owner);
        if (DB::table('wallet_entries')->where('business_key', $key)->exists()) {
            return;
        }
        if ($amount === 0 || $wallet->balance_irr + $amount < $wallet->reserved_irr) {
            throw new BookingFailure('INSUFFICIENT_CREDIT');
        }
        DB::table('wallet_entries')->insert(['owner_hash' => $owner, 'amount_irr' => $amount, 'business_key' => $key, 'kind' => $kind, 'appointment_id' => $appointment, 'refund_id' => $refund, 'created_at' => now()]);
        DB::table('wallet_accounts')->where('owner_hash', $owner)->update(['balance_irr' => $wallet->balance_irr + $amount]);
    }
    public function summary(string $owner): array
    {
        $wallet = DB::table('wallet_accounts')->where('owner_hash', $owner)->first();
        return ['balance_irr' => (int) ($wallet->balance_irr ?? 0), 'reserved_irr' => (int) ($wallet->reserved_irr ?? 0), 'available_irr' => (int) (($wallet->balance_irr ?? 0) - ($wallet->reserved_irr ?? 0))];
    }
}
