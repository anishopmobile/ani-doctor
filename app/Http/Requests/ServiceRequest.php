<?php

namespace App\Http\Requests;

use App\Modules\Identity\Domain\MobileNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('catalog.manage');
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();
        foreach (['price_irr', 'duration_minutes', 'buffer_minutes', 'capacity'] as $key) {
            if (isset($input[$key]) && is_string($input[$key])) {
                $input[$key] = MobileNumber::normalize($input[$key]);
            }
        }
        $this->merge($input);
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:150', 'price_irr' => 'required|integer|min:0|max:100000000000', 'duration_minutes' => 'required|integer|min:5|max:480', 'buffer_minutes' => 'required|integer|min:0|max:240', 'capacity' => 'required|integer|min:1|max:20', 'is_active' => 'required|boolean', 'is_online' => 'required|boolean'];
    }
}
