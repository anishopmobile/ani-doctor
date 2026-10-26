<?php

namespace App\Http\Requests;

use App\Modules\Identity\Domain\MobileNumber;
use Illuminate\Foundation\Http\FormRequest;

class RequestOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['mobile' => is_string($this->mobile) ? MobileNumber::normalize($this->mobile) : $this->mobile]);
    }

    public function rules(): array
    {
        return ['mobile' => ['required', 'string', 'regex:/\A09[0-9]{9}\z/D'], 'purpose' => ['required', 'in:login,guest']];
    }

    public function messages(): array
    {
        return ['mobile.required' => 'شماره موبایل را وارد کنید.', 'mobile.regex' => 'شماره موبایل معتبر با ۰۹ وارد کنید.', 'purpose.in' => 'نوع درخواست معتبر نیست.'];
    }
}
