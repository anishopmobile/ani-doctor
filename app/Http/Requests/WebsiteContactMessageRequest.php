<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class WebsiteContactMessageRequest extends FormRequest
{
    protected $errorBag = 'contact';

    public function authorize(): bool
    {
        $theme = app(\App\Modules\Website\Application\ThemeService::class)->settings();
        return $theme['show_contact'] && $theme['home_contact_form_enabled'];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('contact_mobile'))) {
            $this->merge(['contact_mobile' => strtr(trim($this->input('contact_mobile')), array_combine(mb_str_split('۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩'), str_split('01234567890123456789')))]);
        }
    }

    public function rules(): array
    {
        return [
            'contact_sender' => ['required', 'string', 'max:120'],
            'contact_mobile' => ['required', 'string', 'regex:/\A\+?[0-9]{7,15}\z/D'],
            'contact_message' => ['required', 'string', 'max:2000'],
            'contact_submission' => ['required', 'uuid'],
            'contact_company' => ['nullable', 'string', 'max:0'],
        ];
    }

    public function attributes(): array
    {
        return ['contact_sender' => 'نام', 'contact_mobile' => 'شماره تماس', 'contact_message' => 'متن پیام', 'contact_submission' => 'شناسه ارسال', 'contact_company' => 'فرم تماس'];
    }

    protected function getRedirectUrl(): string
    {
        return route('website.home').'#contact';
    }
}
