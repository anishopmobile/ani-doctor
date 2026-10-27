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
            'brand_name' => ['sometimes', 'required', 'string', 'max:120'],
            'brand_subtitle' => ['sometimes', 'required', 'string', 'max:180'],
            'contact_phone' => ['sometimes', 'required', 'string', 'regex:/\A\+?[0-9]{7,15}\z/D'],
            'contact_address' => ['sometimes', 'required', 'string', 'max:500'],
            'seo_description' => ['sometimes', 'required', 'string', 'max:300'],
            'primary_color' => ['required', 'string', 'regex:/\\A#[0-9a-fA-F]{6}\\z/D'],
            'text_color' => ['required', 'string', 'regex:/\\A#[0-9a-fA-F]{6}\\z/D'],
            'surface_color' => ['required', 'string', 'regex:/\\A#[0-9a-fA-F]{6}\\z/D'],
        ];
    }
}
