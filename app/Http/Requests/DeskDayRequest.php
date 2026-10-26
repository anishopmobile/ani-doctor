<?php

namespace App\Http\Requests;

use App\Modules\Identity\Domain\MobileNumber;
use App\Modules\Scheduling\Application\JalaliDate;
use Illuminate\Foundation\Http\FormRequest;

final class DeskDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('desk.view') ?? false;
    }
    protected function prepareForValidation(): void
    {
        foreach (['date', 'query', 'service_id'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim(MobileNumber::normalize($this->input($field)))]);
            }
        }
    }
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'string', function ($attribute, $value, $fail): void {
                try {
                    app(JalaliDate::class)->parse($value);
                } catch (\InvalidArgumentException) {
                    $fail('روز شمسی معتبر وارد کنید؛ مانند ۱۴۰۵-۰۸-۰۳.');
                }
            }],
            'query' => 'nullable|string|max:100', 'attendance' => 'nullable|in:scheduled,arrived,completed,no_show',
            'service_id' => 'nullable|integer|min:1|exists:services,id', 'clear' => 'sometimes|boolean',
        ];
    }
}
