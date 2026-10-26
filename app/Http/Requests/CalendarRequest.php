<?php

namespace App\Http\Requests;

use App\Modules\Scheduling\Application\JalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class CalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['service_id' => 'required|integer|min:1', 'month' => $this->routeIs('calendar.month', 'appointments.calendar.month') ? ['required', 'string', 'regex:/\A1[34][0-9]{2}-(0[1-9]|1[0-2])\z/'] : 'prohibited', 'date' => $this->routeIs('calendar.day', 'appointments.calendar.day') ? 'required|date_format:Y-m-d' : 'prohibited'];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || !$this->routeIs('calendar.month', 'appointments.calendar.month')) {
                return;
            }
            try {
                app(JalaliDate::class)->month($this->input('month'));
            } catch (\InvalidArgumentException) {
                $validator->errors()->add('month', 'ماه شمسی معتبر نیست.');
            }
        }];
    }
}
