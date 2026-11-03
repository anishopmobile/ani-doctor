<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CardTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route('card_action')) {
            'settings' => ['enabled' => 'required|boolean', 'card_number' => ['required', 'regex:/\A[0-9]{16}\z/'], 'holder' => 'required|string|max:150', 'expected_version' => 'required|integer|min:0'],
            'begin' => ['expected_version' => 'required|integer|min:1'],
            'submit' => ['receipt' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120'],
            'review' => ['decision' => 'required|in:approve,reject', 'reason' => 'required|string|max:500'],
            default => [],
        };
    }
}
