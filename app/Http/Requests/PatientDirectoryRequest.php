<?php

namespace App\Http\Requests;

use App\Modules\Identity\Domain\MobileNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PatientDirectoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('patients.manage') ?? false;
    }
    protected function prepareForValidation(): void
    {
        foreach (['query', 'contact_mobile', 'file_number'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim(MobileNumber::normalize($this->input($field)))]);
            }
        }
    }
    public function rules(): array
    {
        if ($this->route('directory_action') === 'search') {
            return ['query' => 'nullable|string|max:100', 'clear' => 'sometimes|boolean'];
        }
        return ['name' => 'required|string|max:150', 'contact_mobile' => ['nullable', 'string', 'regex:/\A09[0-9]{9}\z/'], 'file_number' => ['nullable', 'string', 'max:64', Rule::unique('patients', 'file_number')->ignore($this->route('patient'))]];
    }
}
