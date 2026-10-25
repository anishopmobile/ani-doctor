<?php

namespace App\Modules\Booking\Infrastructure;

use Illuminate\Database\Eloquent\Model;

final class Appointment extends Model
{
    protected $guarded = [];
    protected $hidden = ['booker_mobile_hash','override_reason'];
    protected function casts(): array
    {
        return ['start_at' => 'immutable_datetime','end_at' => 'immutable_datetime','occupied_until' => 'immutable_datetime','hold_expires_at' => 'immutable_datetime', 'arrived_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'no_show_at' => 'immutable_datetime','is_override' => 'boolean','price_irr' => 'integer','balance_due_irr' => 'integer','version' => 'integer'];
    }
    public function getRouteKeyName(): string
    {
        return 'reference';
    }
    public function patient(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Modules\Patients\Infrastructure\Patient::class);
    }
    public function effectiveStatus(): string
    {
        return $this->status === 'pending_payment' && !$this->hold_expires_at?->isFuture() ? 'expired' : $this->status;
    }
    public function active(): bool
    {
        return in_array($this->effectiveStatus(), ['pending_payment','confirmed'], true);
    }
}
