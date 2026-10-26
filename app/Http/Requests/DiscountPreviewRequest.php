<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DiscountPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return ['service_id' => 'required|integer|min:1', 'discount_code' => ['required', 'string', 'regex:/\A[A-Za-z0-9_-]{3,32}\z/']];
    }
}
