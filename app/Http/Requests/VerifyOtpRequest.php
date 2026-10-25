<?php

namespace App\Http\Requests;

use App\Modules\Identity\Domain\MobileNumber;
use Illuminate\Foundation\Http\FormRequest;

final class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => is_string($this->code) ? MobileNumber::normalize($this->code) : $this->code]);
    }

    public function rules(): array
    {
        return ['code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/D']];
    }

    public function messages(): array
    {
        return ['code.required' => 'کد تأیید را وارد کنید.', 'code.regex' => 'کد تأیید باید شش رقم باشد.'];
    }
}
