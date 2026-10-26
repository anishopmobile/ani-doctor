<?php

namespace App\Http\Requests;

use App\Modules\Booking\Application\ActorResolver;
use App\Modules\Identity\Domain\MobileNumber;
use Illuminate\Foundation\Http\FormRequest;

class BookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        app(ActorResolver::class)->resolve($this);
        return true;
    }
    protected function prepareForValidation(): void
    {
        $input = $this->all();
        foreach (['booker_mobile','contact_mobile','service_id','patient_id','expected_price_irr','expected_version'] as $key) {
            if (isset($input[$key]) && is_string($input[$key])) {
                $input[$key] = MobileNumber::normalize($input[$key]);
            }
        }
        if (isset($input['discount_code']) && is_string($input['discount_code'])) {
            $input['discount_code'] = strtoupper(trim($input['discount_code']));
        }
        $this->merge($input);
    }
    public function rules(): array
    {
        $action = $this->route('booking_action');
        $create = $action === 'create';
        $move = $action === 'reschedule';
        $staff = app(ActorResolver::class)->resolve($this)->staff();
        return [
            'idempotency_key' => 'required|uuid',
            'discount_code' => $create ? ['nullable', 'string', 'regex:/\A[A-Za-z0-9_-]{3,32}\z/'] : 'prohibited',
            'expected_version' => $create ? 'prohibited' : 'required|integer|min:1',
            'service_id' => $create ? 'required|integer|min:1' : 'prohibited',
            'expected_price_irr' => $create ? 'required|integer|min:0' : 'prohibited',
            'start_at' => ($create || $move) ? ['required','string','date','regex:/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(Z|[+-][0-9]{2}:[0-9]{2})\z/'] : 'prohibited',
            'patient_id' => $create ? 'nullable|integer|min:1' : 'prohibited',
            'patient_name' => $create ? 'required_without:patient_id|nullable|string|max:150' : 'prohibited',
            'contact_mobile' => $create ? ['nullable','string','regex:/\A09[0-9]{9}\z/'] : 'prohibited',
            'booker_mobile' => ($create && $staff) ? ['required','string','regex:/\A09[0-9]{9}\z/'] : 'prohibited',
            'override_capacity' => ($create || $move) ? 'sometimes|boolean' : 'prohibited',
            'reason' => $action === 'cancel' || $this->boolean('override_capacity') ? 'required|string|max:500' : 'nullable|string|max:500',
        ];
    }
}
