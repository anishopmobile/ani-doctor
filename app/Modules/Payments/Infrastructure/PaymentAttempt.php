<?php

namespace App\Modules\Payments\Infrastructure;

use Illuminate\Database\Eloquent\Model;

final class PaymentAttempt extends Model
{
    protected $guarded = [];
    protected $hidden = ['merchant_secret', 'authority', 'owner_hash', 'actor_hash', 'payload_hash'];
    protected function casts(): array
    {
        return ['merchant_secret' => 'encrypted', 'sandbox' => 'boolean', 'amount_irr' => 'integer', 'credit_irr' => 'integer', 'credit_reserved' => 'boolean', 'hold_expires_at' => 'immutable_datetime'];
    }
}
