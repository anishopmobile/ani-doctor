<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class WebsiteArticleRequest extends FormRequest
{
    private bool $invalidPersianDate = false;

    protected function prepareForValidation(): void
    {
        if (!$this->has('published_jalali')) {
            return;
        }
        $value = $this->input('published_jalali');
        $time = $this->input('published_time') ?: '00:00';
        $date = null;
        if (is_string($value) && $value !== '') {
            $value = str_replace('/', '-', \App\Modules\Identity\Domain\MobileNumber::normalize($value));
            $this->merge(['published_jalali' => $value]);
            try {
                $day = app(\App\Modules\Scheduling\Application\JalaliDate::class)->parse($value);
                if (is_string($time) && preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]\z/D', $time)) {
                    $date = $day->setTimeFromTimeString($time)->utc()->format('Y-m-d H:i:s');
                }
            } catch (\InvalidArgumentException) {
                $this->invalidPersianDate = true;
            }
        }
        $this->merge(['published_at' => $date]);
    }

    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator): void {
            if ($this->invalidPersianDate) {
                $validator->errors()->add('published_jalali', 'تاریخ انتشار شمسی معتبر نیست.');
            }
        }];
    }
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('website.manage') === true;
    }

    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'regex:/\\A[a-z0-9]+(?:-[a-z0-9]+)*\\z/D', 'max:120', Rule::unique('website_articles', 'slug')->ignore($this->route('article')?->id)],
            'title' => ['required', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:350'],
            'body' => ['required', 'string', 'max:50000'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'is_published' => ['required', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'published_jalali' => ['sometimes', 'nullable', 'string', 'max:10'],
            'published_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'taxonomy_ids' => ['sometimes', 'array', 'max:30'],
            'taxonomy_ids.*' => ['integer', 'distinct', Rule::exists('website_taxonomies', 'id')],
        ];
    }
}
