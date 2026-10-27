<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class WebsiteTaxonomyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('website.manage') === true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:category,tag'],
            'slug' => ['required', 'string', 'max:120', 'regex:/\\A[a-z0-9]+(?:-[a-z0-9]+)*\\z/D',
                Rule::unique('website_taxonomies', 'slug')->where('type', $this->input('type'))],
            'name' => ['required', 'string', 'max:120'],
        ];
    }
}
