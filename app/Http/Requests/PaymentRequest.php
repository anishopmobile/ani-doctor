<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // VerifiedBooker plus use-case authorization run independently.
    }
    protected function prepareForValidation(): void
    {
        foreach (['credit_irr', 'expected_version', 'amount_irr', 'minutes'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => strtr($this->input($field), array_combine(preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY), str_split('01234567890123456789')))]);
            }
        }
    }
    public function rules(): array
    {
        return match ($this->route('finance_action')) {
            'initiate' => ['idempotency_key' => 'required|uuid', 'expected_version' => 'required|integer|min:1', 'credit_irr' => 'required|integer|min:0|max:100000000000'],
            'emergency' => ['expected_version' => 'required|integer|min:1', 'reason' => 'required|string|max:500'],
            'collect' => ['amount_irr' => 'required|integer|min:1|max:100000000000', 'receipt' => 'required|uuid', 'method' => 'required|in:cash,card,transfer', 'reason' => 'required|string|max:500'],
            'settings' => ['enabled' => 'required|boolean', 'minutes' => 'required|integer|min:1|max:1440', 'reason' => 'required|string|max:500'],
            'refund' => ['idempotency_key' => 'required|uuid', 'amount_irr' => 'required|integer|min:1|max:100000000000', 'reason' => 'required|string|max:500'],
            'decision' => ['decision' => 'required|in:processing,rejected,completed', 'reason' => 'required|string|max:500', 'bank_reference' => ['nullable', 'required_if:decision,completed', 'string', 'regex:/^[a-zA-Z0-9_-]{3,100}$/D']],
            default => [],
        };
    }
    public function attributes(): array
    {
        return ['credit_irr' => 'اعتبار مصرفی (ریال)', 'amount_irr' => 'مبلغ (ریال)', 'minutes' => 'مدت فعال‌سازی', 'reason' => 'دلیل', 'bank_reference' => 'شماره پیگیری بانکی', 'receipt' => 'شناسه رسید', 'method' => 'روش دریافت'];
    }
}
