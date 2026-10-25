<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('attendance.manage') ?? false;
    }
    public function rules(): array
    {
        return ['idempotency_key' => 'required|uuid', 'expected_version' => 'required|integer|min:1', 'action' => 'required|in:scheduled,arrived,completed,no_show', 'reason' => 'required|string|max:500'];
    }
}
