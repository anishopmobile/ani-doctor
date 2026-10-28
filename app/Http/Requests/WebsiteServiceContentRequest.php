<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class WebsiteServiceContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('website.manage') === true;
    }

    public function rules(): array
    {
        return [
            'summary' => ['nullable', 'string', 'max:350'],
            'description' => ['nullable', 'string', 'max:10000'],
            'is_published' => ['required', 'boolean'],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
