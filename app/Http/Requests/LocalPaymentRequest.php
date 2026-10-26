<?php

namespace App\Http\Requests;

use App\Modules\Payments\Application\LocalPaymentMode;
use Illuminate\Foundation\Http\FormRequest;

final class LocalPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(LocalPaymentMode::class)->enabled();
    }

    public function rules(): array
    {
        return ['outcome' => 'required|in:success,failure'];
    }
}
