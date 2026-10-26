<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StaffDisplayNameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('reports.finance') ?? false;
    }
    public function rules(): array
    {
        return ['user_id' => 'required|integer|exists:users,id', 'display_name' => 'required|string|min:2|max:100', 'reason' => 'required|string|max:500'];
    }
    public function attributes(): array
    {
        return ['user_id' => 'اپراتور', 'display_name' => 'نام نمایشی'];
    }
}
