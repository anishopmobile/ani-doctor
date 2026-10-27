<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class WebsiteArticleRequest extends FormRequest
{
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
        ];
    }
}
