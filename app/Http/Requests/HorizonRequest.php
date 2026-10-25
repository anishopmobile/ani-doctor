<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class HorizonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('schedules.settings');
    }

    public function rules(): array
    {
        return ['horizon_days' => 'required|integer|between:1,365'];
    }
}
