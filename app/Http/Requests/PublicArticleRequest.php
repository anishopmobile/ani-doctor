<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PublicArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:120', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D'],
            'tag' => ['nullable', 'string', 'max:120', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'title'])],
            'period' => ['nullable', Rule::in(['all', 'month', 'year'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
