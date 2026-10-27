<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class WebsitePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('website.manage') === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('blocks_json')) {
            $raw = $this->input('blocks_json');
            try {
                $blocks = is_string($raw) && strlen($raw) <= 150000 ? json_decode($raw, true, 64, JSON_THROW_ON_ERROR) : null;
            } catch (\JsonException) {
                $blocks = null;
            }
            $this->merge(['blocks' => $blocks]);
        }
    }

    public function rules(): array
    {
        $editing = $this->route('page') !== null;

        return ['title' => 'required|string|max:180', 'meta_description' => 'nullable|string|max:300',
            'slug' => $editing ? 'prohibited' : ['required', 'string', 'max:100', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', 'unique:website_pages,slug'],
            'expected_version' => $editing ? 'required|integer|min:1' : 'prohibited',
            'blocks' => $editing ? 'required|array|min:1|max:40' : 'prohibited'];
    }
}
