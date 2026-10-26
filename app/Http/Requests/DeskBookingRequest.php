<?php

namespace App\Http\Requests;

use App\Modules\Identity\Domain\MobileNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class DeskBookingRequest extends BookingRequest
{
    public function authorize(): bool
    {
        parent::authorize();
        return Gate::allows('desk.book');
    }
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        foreach (['urgent_time', 'urgent_date', 'received_irr'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => MobileNumber::normalize($this->input($field))]);
            }
        }
        $time = $this->input('urgent_time');
        $date = $this->input('urgent_date');
        if ($this->input('booking_kind') === 'urgent' && is_string($date) && is_string($time) && preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/', $date, $parts) && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) && preg_match('/\A([01][0-9]|2[0-3]):[0-5][0-9]\z/', $time)) {
            $this->merge(['start_at' => CarbonImmutable::parse($date.' '.$time, 'Asia/Tehran')->utc()->toIso8601String()]);
        }
    }
    public function rules(): array
    {
        return [...parent::rules(),
            'booking_kind' => 'required|in:phone,urgent',
            'urgent_date' => 'exclude_unless:booking_kind,urgent|required|date_format:Y-m-d',
            'urgent_time' => ['exclude_unless:booking_kind,urgent', 'required', 'string', 'regex:/\A([01][0-9]|2[0-3]):[0-5][0-9]\z/'],
            'file_number' => [Rule::excludeIf($this->filled('patient_id')), 'nullable', 'string', 'max:64', 'unique:patients,file_number'],
            'reason' => 'required|string|max:500',
            'settlement' => 'required|in:hold,clinic_paid,clinic_due',
            'received_irr' => 'exclude_unless:settlement,clinic_paid|required|integer|min:0|max:100000000000',
            'receipt' => 'exclude_unless:settlement,clinic_paid|required|uuid',
            'method' => 'exclude_unless:settlement,clinic_paid|required|in:cash,card,transfer',
        ];
    }
    public function attributes(): array
    {
        return ['urgent_time' => 'ساعت فوری امروز', 'urgent_date' => 'روز نوبت فوری', 'file_number' => 'شماره پرونده', 'received_irr' => 'مبلغ دریافت‌شده', 'reason' => 'توضیح ثبت', 'settlement' => 'روش تسویه'];
    }
}
