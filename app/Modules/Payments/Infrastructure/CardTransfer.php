<?php

namespace App\Modules\Payments\Infrastructure;

use Illuminate\Database\Eloquent\Model;

final class CardTransfer extends Model
{
    protected $guarded = [];
    protected $hidden = ['receipt_path', 'receipt_hash', 'reason'];

    protected function casts(): array
    {
        return ['deadline' => 'immutable_datetime', 'submitted_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime', 'amount_irr' => 'integer', 'duplicate_receipt' => 'boolean', 'reason' => 'encrypted'];
    }
}
