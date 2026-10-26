<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PrivateFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Ownership/independent permission is checked against the locked appointment.
    }
    public function rules(): array
    {
        return ['version_id' => 'required|integer|min:1', 'expected_submission_id' => 'required|integer|min:0', 'idempotency_key' => 'required|uuid', 'consent' => 'required|accepted', 'answers' => 'sometimes|array|max:30'];
    }
    public function attributes(): array
    {
        return ['version_id' => 'نسخه فرم', 'expected_submission_id' => 'نسخه پاسخ', 'consent' => 'اطلاع و اجازه ثبت', 'answers' => 'پاسخ‌ها'];
    }
}
