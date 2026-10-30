<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class WebsiteLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('website.manage') === true;
    }

    public function rules(): array
    {
        return [
            'placement' => ['required', 'in:header,footer,social,footer_legal'],
            'label' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || !\App\Modules\Website\Application\LinkService::validUrl($value, $this->input('placement') === 'social')) {
                    $fail($this->input('placement') === 'social' ? 'لینک شبکه اجتماعی باید یک نشانی HTTPS معتبر باشد.' : 'لینک باید داخلی یا یک آدرس HTTPS معتبر باشد.');
                }
            }],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_published' => ['required', 'boolean'],
            'new_tab' => ['required', 'boolean'],
        ];
    }
}
