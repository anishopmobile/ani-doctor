<?php

namespace App\Http\Requests;

use App\Modules\Identity\Domain\MobileNumber;
use App\Modules\Scheduling\Application\JalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

final class DiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('discounts.manage') ?? false;
    }
    protected function prepareForValidation(): void
    {
        foreach (['code', 'value', 'max_discount_irr', 'max_uses', 'per_patient', 'start_jalali', 'end_jalali'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => MobileNumber::normalize($this->input($key))]);
            }
        }
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }
    public function rules(): array
    {
        if ($this->route('discount')) {
            return ['expected_version' => 'required|integer|min:1', 'is_active' => 'required|boolean', 'reason' => 'required|string|max:500'];
        }
        return ['code' => ['required', 'string', 'regex:/\A[A-Z0-9_-]{3,32}\z/', 'unique:discounts,code'], 'kind' => 'required|in:percent,fixed', 'value' => 'required|integer|min:1|max:'.($this->input('kind') === 'percent' ? '100' : '100000000000'), 'max_discount_irr' => 'nullable|integer|min:1|max:100000000000', 'max_uses' => 'required|integer|min:1|max:1000000', 'per_patient' => 'required|integer|min:1|max:10000', 'start_jalali' => 'required|string|max:10', 'end_jalali' => 'required|string|max:10', 'service_ids' => 'required|array|min:1|max:100', 'service_ids.*' => 'required|integer|distinct|exists:services,id', 'reason' => 'required|string|max:500'];
    }
    public function offer(): array
    {
        $data = $this->validated();
        try {
            $start = app(JalaliDate::class)->parse($data['start_jalali']);
            $end = app(JalaliDate::class)->parse($data['end_jalali'])->addDay();
            if ($end->lessThanOrEqualTo($start)) {
                throw new \InvalidArgumentException();
            }
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['start_jalali' => 'بازه تاریخ شمسی معتبر نیست.']);
        }
        return [...$data, 'starts_at' => $start->utc(), 'ends_at' => $end->utc()];
    }
    public function attributes(): array
    {
        return ['code' => 'کد تخفیف', 'value' => 'مقدار تخفیف', 'kind' => 'نوع تخفیف', 'max_discount_irr' => 'سقف مبلغ ریالی', 'max_uses' => 'سقف کل مصرف', 'per_patient' => 'سقف هر بیمار', 'start_jalali' => 'شروع اعتبار', 'end_jalali' => 'پایان اعتبار', 'service_ids' => 'خدمات مجاز'];
    }
}
