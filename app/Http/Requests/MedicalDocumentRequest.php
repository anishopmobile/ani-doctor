<?php

namespace App\Http\Requests;

use App\Policies\MedicalRecordPolicy;
use Illuminate\Foundation\Http\FormRequest;

final class MedicalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && app(MedicalRecordPolicy::class)->administrative($this->user());
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'max:'.config('medical-records.max_file_kb'), 'mimetypes:'.implode(',', config('medical-records.mime_types'))], 'request_key' => 'required|uuid', 'title' => 'nullable|string|max:150', 'category' => 'nullable|in:laboratory,ultrasound,pathology,admission,prescription,fertility,other', 'source' => 'nullable|string|max:250', 'document_jalali' => 'nullable|string|max:10', 'description' => 'nullable|string|max:2000', 'visit_id' => 'nullable|integer|min:1', 'episode_id' => 'nullable|integer|min:1'];
    }

    public function messages(): array
    {
        return ['file.max' => 'حجم فایل بیشتر از حد مجاز است.', 'file.mimetypes' => 'فقط PDF، JPEG یا PNG معتبر پذیرفته می‌شود.'];
    }
}
