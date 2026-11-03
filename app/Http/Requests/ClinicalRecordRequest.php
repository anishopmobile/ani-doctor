<?php

namespace App\Http\Requests;

use App\Modules\MedicalRecords\Application\ClinicalSchema;
use App\Modules\Scheduling\Application\JalaliDate;
use App\Policies\MedicalRecordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ClinicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && app(MedicalRecordPolicy::class)->clinical($this->user());
    }

    public function rules(): array
    {
        if ($this->routeIs('emr.sign')) {
            return ['expected_version' => 'required|integer|min:1'];
        }
        $fields = ClinicalSchema::fields((string) ($this->route('kind') ?? $this->route('record')?->kind));
        $rules = ['payload' => 'required|array:'.implode(',', array_keys($fields)), 'request_key' => 'required|uuid', 'occurred_jalali' => 'required|string|max:10', 'occurred_time' => 'required|date_format:H:i', 'visit_id' => 'nullable|integer|min:1', 'episode_id' => 'nullable|integer|min:1', 'appointment_id' => 'nullable|integer|min:1', 'reason' => 'nullable|string|max:2000', 'parent_id' => 'nullable|integer|min:1', 'expected_version' => 'nullable|integer|min:0'];
        foreach ($fields as $field => $definition) {
            $rules['payload.'.$field] = $definition[2] === 'date'
                ? ['nullable', 'string', 'max:10', function (string $attribute, mixed $value, \Closure $fail): void {
                    if (!is_string($value)) {
                        return;
                    }
                    try {
                        app(JalaliDate::class)->parse(\App\Modules\Identity\Domain\MobileNumber::normalize($value));
                    } catch (\InvalidArgumentException) {
                        $fail('تاریخ شمسی معتبر وارد کنید.');
                    }
                }]
                : $definition[1];
        }
        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->routeIs('emr.sign')) {
                return;
            }
            if (!is_string($this->input('occurred_jalali'))) {
                return; // The string rule reports malformed scalar/array input without a PHP warning.
            }
            try {
                app(JalaliDate::class)->parse(\App\Modules\Identity\Domain\MobileNumber::normalize((string) $this->input('occurred_jalali')));
            } catch (\InvalidArgumentException) {
                $validator->errors()->add('occurred_jalali', 'تاریخ شمسی معتبر وارد کنید.');
            }
            $payload = $this->input('payload', []);
            if (!is_array($payload)) {
                return;
            }
            $kind = $this->route('kind') ?? $this->route('record')?->kind;
            if ($kind === 'pregnancy') {
                if (($payload['dating_source'] ?? 'unknown') !== 'unknown' && empty($payload['dating_decision'])) {
                    $validator->errors()->add('payload.dating_decision', 'منبع و تصمیم پزشک برای dating لازم است.');
                }
                if ((!empty($payload['edd']) || isset($payload['gestation_days'])) && ($payload['dating_source'] ?? 'unknown') === 'unknown') {
                    $validator->errors()->add('payload.dating_source', 'برای تاریخ یا سن ثبت‌شده، مبنا را مشخص کنید.');
                }
                if (($payload['gestation_days'] ?? null) !== null && empty($payload['dating_date'])) {
                    $validator->errors()->add('payload.dating_date', 'تاریخ مرجع سن بارداری لازم است.');
                }
                if (($payload['episode_status'] ?? '') === 'ended' && empty($payload['outcome_date'])) {
                    $validator->errors()->add('payload.outcome_date', 'تاریخ پایان دوره لازم است.');
                }
            }
            if ($kind === 'followup' && !$this->filled('episode_id') && !$this->route('record')?->episode_id) {
                $validator->errors()->add('episode_id', 'دوره بارداری را انتخاب کنید.');
            }
            if ($kind === 'prescription' && !$this->filled('visit_id') && !$this->route('record')?->visit_id) {
                $validator->errors()->add('visit_id', 'نسخه باید به ویزیت متصل باشد.');
            }
        }];
    }

    public function messages(): array
    {
        return ['*.required' => 'این فیلد لازم است.', '*.max' => 'مقدار از حد مجاز بیشتر است.', '*.in' => 'یکی از گزینه‌های مجاز را انتخاب کنید.', '*.date_format' => 'تاریخ یا زمان معتبر وارد کنید.', 'payload.array' => 'ساختار اطلاعات معتبر نیست.'];
    }
}
