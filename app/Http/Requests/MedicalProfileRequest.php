<?php

namespace App\Http\Requests;

use App\Modules\Identity\Domain\MobileNumber;
use App\Modules\Scheduling\Application\JalaliDate;
use App\Policies\MedicalRecordPolicy;
use Illuminate\Foundation\Http\FormRequest;

final class MedicalProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && app(MedicalRecordPolicy::class)->administrative($this->user());
    }

    public static function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', strtr(MobileNumber::normalize($value), ['ي' => 'ی', 'ك' => 'ک', 'ى' => 'ی'])));
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'contact_mobile', 'identity_number', 'query'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => self::normalize($this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        if ($this->routeIs('emr.search')) {
            return ['query' => 'nullable|string|max:100', 'status' => 'nullable|in:active,archived', 'from' => 'nullable|string|max:10', 'to' => 'nullable|string|max:10', 'sort' => 'nullable|in:newest,oldest,name,file'];
        }
        return ['name' => 'required|string|max:150', 'contact_mobile' => 'nullable|regex:/\A09[0-9]{9}\z/', 'identity_type' => 'required|in:unknown,national,passport,other', 'identity_number' => 'nullable|string|max:64', 'birth_jalali' => 'nullable|string|max:10', 'marital_status' => 'required|in:unknown,single,married,other', 'address' => 'nullable|string|max:2000', 'administrative_note' => 'nullable|string|max:2000', 'status' => 'required|in:active,archived', 'expected_version' => 'required|integer|min:0', 'duplicate_ack' => 'nullable|accepted'];
    }

    public function after(): array
    {
        return [function ($validator): void {
            foreach ($this->routeIs('emr.search') ? ['from', 'to'] : ['birth_jalali'] as $field) {
                if ($this->filled($field) && is_string($this->input($field))) {
                    try {
                        $date = app(JalaliDate::class)->parse(MobileNumber::normalize((string) $this->input($field)));
                        if ($field === 'birth_jalali' && $date->isFuture()) {
                            $validator->errors()->add($field, 'تاریخ تولد نمی‌تواند در آینده باشد.');
                        }
                    } catch (\InvalidArgumentException) {
                        $validator->errors()->add($field, 'تاریخ شمسی معتبر وارد کنید.');
                    }
                }
            }
            if ($this->input('identity_type') === 'national' && $this->filled('identity_number') && is_string($this->input('identity_number'))) {
                $id = (string) $this->input('identity_number');
                $valid = preg_match('/\A[0-9]{10}\z/', $id) && !preg_match('/\A([0-9])\1{9}\z/', $id);
                $sum = 0;
                if ($valid) {
                    for ($i = 0; $i < 9; $i++) {
                        $sum += (int) $id[$i] * (10 - $i);
                    }
                    $remainder = $sum % 11;
                    $valid = (int) $id[9] === ($remainder < 2 ? $remainder : 11 - $remainder);
                }
                if (!$valid) {
                    $validator->errors()->add('identity_number', 'کد ملی معتبر نیست؛ برای شناسه غیرایرانی نوع مناسب را انتخاب کنید.');
                }
            }
        }];
    }

    public function messages(): array
    {
        return ['required' => 'این فیلد لازم است.', 'regex' => 'شماره تماس معتبر نیست.', 'in' => 'گزینه معتبر انتخاب کنید.', 'max' => 'مقدار از حد مجاز بیشتر است.'];
    }
}
