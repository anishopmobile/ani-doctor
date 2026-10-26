<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class FormPublishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('forms.manage') ?? false;
    }
    protected function prepareForValidation(): void
    {
        if (is_array($this->input('schema'))) {
            $schema = $this->input('schema');
            foreach ($schema as &$field) {
                if (!is_array($field)) {
                    continue;
                }
                if (array_key_exists('options_text', $field)) {
                    if (is_string($field['options_text']) && in_array($field['type'] ?? '', ['select', 'multiselect'], true)) {
                        $field['options'] = array_values(array_filter(array_map('trim', explode("\n", $field['options_text'])), fn ($v) => $v !== ''));
                    }
                    unset($field['options_text']);
                }
                if (isset($field['condition']) && is_array($field['condition']) && empty($field['condition']['field'])) {
                    unset($field['condition']);
                }
                foreach (['min', 'max'] as $key) {
                    if (($field[$key] ?? null) === '' || (array_key_exists($key, $field) && $field[$key] === null)) {
                        unset($field[$key]);
                    }
                }
            }
            unset($field);
            $this->merge(['schema' => array_values($schema)]);
        }
    }
    public function rules(): array
    {
        return ['title' => 'required|string|max:150', 'service_id' => 'nullable|required_without:page_key|prohibits:page_key|integer|exists:services,id', 'page_key' => 'nullable|required_without:service_id|prohibits:service_id|in:appointment', 'is_active' => 'required|boolean', 'expected_version' => 'required|integer|min:0', 'reason' => 'required|string|max:500', 'schema' => 'required|array|min:1|max:30'];
    }
    public function messages(): array
    {
        return ['service_id.required_without' => 'خدمت یا صفحه خصوصی را انتخاب کنید.', 'page_key.required_without' => 'خدمت یا صفحه خصوصی را انتخاب کنید.', 'service_id.prohibits' => 'فقط خدمت یا صفحه را انتخاب کنید، نه هر دو.', 'page_key.prohibits' => 'فقط خدمت یا صفحه را انتخاب کنید، نه هر دو.'];
    }
    public function attributes(): array
    {
        return ['title' => 'عنوان فرم', 'page_key' => 'صفحه خصوصی', 'expected_version' => 'نسخه فرم', 'schema' => 'فیلدهای فرم'];
    }
}
