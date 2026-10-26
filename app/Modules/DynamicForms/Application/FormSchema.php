<?php

namespace App\Modules\DynamicForms\Application;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class FormSchema
{
    public function definition(array $schema): array
    {
        Validator::make(['fields' => $schema], [
            'fields' => 'required|array|min:1|max:30',
            'fields.*' => 'array:key,label,type,required,options,condition,min,max',
            'fields.*.key' => ['required', 'string', 'regex:/\A[a-z][a-z0-9_]{0,39}\z/', 'distinct'],
            'fields.*.label' => 'required|string|max:150',
            'fields.*.type' => 'required|in:text,number,date,select,multiselect,checkbox',
            'fields.*.required' => 'required|boolean',
            'fields.*.options' => 'sometimes|array|min:1|max:20',
            'fields.*.options.*' => 'required|string|max:100',
            'fields.*.condition' => 'sometimes|array:field,equals',
            'fields.*.condition.field' => 'required_with:fields.*.condition|string',
            'fields.*.condition.equals' => 'required_with:fields.*.condition',
            'fields.*.min' => 'sometimes|integer|min:-1000000|max:1000000',
            'fields.*.max' => 'sometimes|integer|min:-1000000|max:1000000',
        ], [], ['fields' => 'فیلدهای فرم', 'fields.*' => 'ساختار فیلد', 'fields.*.key' => 'شناسه فیلد', 'fields.*.label' => 'عنوان فیلد', 'fields.*.type' => 'نوع پاسخ', 'fields.*.required' => 'وضعیت اجباری', 'fields.*.options' => 'گزینه‌ها', 'fields.*.options.*' => 'گزینه', 'fields.*.condition' => 'شرط نمایش', 'fields.*.condition.field' => 'فیلد شرط', 'fields.*.condition.equals' => 'مقدار شرط', 'fields.*.min' => 'حداقل', 'fields.*.max' => 'حداکثر'])->validate();
        $previous = [];
        foreach ($schema as $field) {
            if (in_array($field['type'], ['select', 'multiselect'], true) && empty($field['options'])) {
                throw ValidationException::withMessages(['schema' => 'گزینه‌های فیلد انتخابی لازم‌اند.']);
            }
            if (isset($field['options']) && count($field['options']) !== count(array_unique($field['options']))) {
                throw ValidationException::withMessages(['schema' => 'گزینه‌های یک فیلد نباید تکراری باشند.']);
            }
            if (isset($field['min'], $field['max']) && $field['min'] > $field['max']) {
                throw ValidationException::withMessages(['schema' => 'حداقل باید کوچک‌تر از حداکثر باشد.']);
            }
            if (isset($field['condition'])) {
                $source = $previous[$field['condition']['field']] ?? null;
                if (!$source || !in_array($source['type'], ['select', 'checkbox'], true) || (is_array($field['condition']['equals']) || is_object($field['condition']['equals'])) || ($source['type'] === 'select' && !in_array($field['condition']['equals'], $source['options'], true)) || ($source['type'] === 'checkbox' && !in_array($field['condition']['equals'], [true, false, 0, 1, '0', '1'], true))) {
                    throw ValidationException::withMessages(['schema' => 'شرط باید به یک انتخاب یا checkbox قبلی و مقدار معتبر اشاره کند.']);
                }
            }
            $previous[$field['key']] = $field;
        }
        return array_values($schema);
    }
    public function visible(array $field, array $answers): bool
    {
        if (!isset($field['condition'])) {
            return true;
        }
        $condition = $field['condition'];
        $canonical = fn ($value) => is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        return array_key_exists($condition['field'], $answers) && $canonical($answers[$condition['field']]) === $canonical($condition['equals']);
    }
    public function answers(array $schema, array $answers): array
    {
        $known = array_column($schema, 'key');
        if (array_diff(array_keys($answers), $known)) {
            throw ValidationException::withMessages(['answers' => 'فیلد ناشناخته در پاسخ وجود دارد.']);
        }
        $clean = [];
        foreach ($schema as $field) {
            $key = $field['key'];
            if (!$this->visible($field, $clean)) {
                continue; // Hidden values are deliberately discarded on the server.
            }
            $rules = [$field['required'] ? 'required' : 'nullable'];
            $rules = [...$rules, ...match ($field['type']) {
                'text' => ['string', 'max:2000'],
                'number' => ['numeric', 'between:'.($field['min'] ?? -1000000).','.($field['max'] ?? 1000000)],
                'date' => ['date_format:Y-m-d'],
                'select' => ['string', Rule::in($field['options'])],
                'multiselect' => ['array', 'max:20'],
                'checkbox' => ['boolean'],
            }];
            $validation = [$key => $rules];
            if ($field['type'] === 'multiselect') {
                $validation[$key.'.*'] = ['string', 'distinct', Rule::in($field['options'])];
            }
            $value = $answers[$key] ?? null;
            if (is_string($value) && in_array($field['type'], ['number', 'date'], true)) {
                $value = \App\Modules\Identity\Domain\MobileNumber::normalize($value);
            }
            if ($field['type'] === 'date' && is_string($value) && preg_match('/\A1[34][0-9]{2}-/', $value)) {
                try {
                    $value = app(\App\Modules\Scheduling\Application\JalaliDate::class)->parse($value)->toDateString();
                } catch (\InvalidArgumentException) {
                    throw ValidationException::withMessages([$key => $field['label'].' باید تاریخ شمسی معتبر باشد.']);
                }
            }
            Validator::make([$key => $value], $validation, [], [$key => $field['label']])->validate();
            if ($value !== null) {
                $clean[$key] = $field['type'] === 'checkbox' ? (bool) $value : $value;
            }
        }
        return $clean;
    }
}
