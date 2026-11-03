<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MedicalInboxReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('medical.review') ?? false;
    }

    public function rules(): array
    {
        return ['decision' => 'required|in:accepted,rejected', 'response' => 'required|string|max:3000'];
    }
}
