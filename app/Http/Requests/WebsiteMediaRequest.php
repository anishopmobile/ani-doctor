<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class WebsiteMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('website.manage') === true;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'alt_text' => ['required', 'string', 'max:180'],
        ];
    }
}
