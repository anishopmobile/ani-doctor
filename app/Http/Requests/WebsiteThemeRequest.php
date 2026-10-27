<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class WebsiteThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('website.manage') === true;
    }

    public function rules(): array
    {
        return [
            'primary_color' => ['required', 'string', 'regex:/\\A#[0-9a-fA-F]{6}\\z/D'],
            'text_color' => ['required', 'string', 'regex:/\\A#[0-9a-fA-F]{6}\\z/D'],
            'surface_color' => ['required', 'string', 'regex:/\\A#[0-9a-fA-F]{6}\\z/D'],
        ];
    }
}
