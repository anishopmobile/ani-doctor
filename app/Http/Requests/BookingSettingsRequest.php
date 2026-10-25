<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class BookingSettingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $data = $this->all();
        foreach (['hold_minutes','cutoff_hours','max_active_patient','max_active_booker'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = \App\Modules\Identity\Domain\MobileNumber::normalize($data[$field]);
            }
        }
        $this->merge($data);
    }
    public function authorize(): bool
    {
        return Gate::allows('booking.settings');
    }
    public function rules(): array
    {
        return ['hold_minutes' => 'required|integer|between:1,60','cutoff_hours' => 'required|integer|between:0,168','max_active_patient' => 'required|integer|between:1,20','max_active_booker' => 'required|integer|between:1,20'];
    }
}
