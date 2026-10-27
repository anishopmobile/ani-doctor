<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class WebsiteSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('website.manage') === true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:hero,about,cta,slide'],
            'title' => ['required', 'string', 'max:180'],
            'subtitle' => ['nullable', 'string', 'max:240'],
            'body' => ['nullable', 'string', 'max:3000'],
            'button_text' => ['nullable', 'string', 'max:80', 'required_with:button_url'],
            'button_url' => ['nullable', 'string', 'max:255', 'regex:/^(\\/[^\\/]|#)[^\\s]*$/', 'required_with:button_text'],
            'background' => ['required', 'in:plain,blush,lilac'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_published' => ['required', 'boolean'],
        ];
    }
}
