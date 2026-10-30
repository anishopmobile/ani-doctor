<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class WebsiteThemeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['highlight_items', 'why_items', 'faq_items', 'footer_features', 'home_about_features', 'home_about_stats', 'home_services_highlights'] as $key) {
            if ($this->input($key.'_present') === '1' && !$this->has($key)) {
                $this->merge([$key => []]);
            }
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('website.manage') === true;
    }

    public function rules(): array
    {
        $rules = [
            'brand_name' => ['sometimes', 'required', 'string', 'max:120'],
            'brand_subtitle' => ['sometimes', 'required', 'string', 'max:180'],
            'contact_phone' => ['sometimes', 'required', 'string', 'regex:/\A\+?[0-9]{7,15}\z/D'],
            'contact_address' => ['sometimes', 'required', 'string', 'max:500'],
            'seo_description' => ['sometimes', 'required', 'string', 'max:300'],
            'logo_is_published' => ['sometimes', 'required', 'boolean'],
            'portrait_is_published' => ['sometimes', 'required', 'boolean'],
            'favicon_is_published' => ['sometimes', 'required', 'boolean'],
            'about_is_published' => ['sometimes', 'required', 'boolean'],
            'services_is_published' => ['sometimes', 'required', 'boolean'],
            'contact_is_published' => ['sometimes', 'required', 'boolean'],
            'home_contact_layout' => ['sometimes', 'required', 'in:clinic,dark,pastel'],
            'home_contact_form_enabled' => ['sometimes', 'required', 'boolean'],
            'home_services_layout' => ['sometimes', 'required', 'in:light,dark,pastel'],
            'home_about_layout' => ['sometimes', 'required', 'in:classic,dark,organic'],
            'expected_settings_version' => ['sometimes', 'integer', 'min:0'],
            'primary_color' => ['required', 'string', 'regex:/\\A#[0-9a-fA-F]{6}\\z/D'],
            'text_color' => ['required', 'string', 'regex:/\\A#[0-9a-fA-F]{6}\\z/D'],
            'surface_color' => ['required', 'string', 'regex:/\\A#[0-9a-fA-F]{6}\\z/D'],
        ];
        foreach (\App\Modules\Website\Application\SiteContent::FIELDS as $key => $field) {
            $rules[$key] = ['sometimes', 'nullable', 'string', 'max:'.$field[2]];
            if (str_ends_with($key, '_url') && (str_starts_with($key, 'hero_') || str_starts_with($key, 'home_about_'))) {
                $rules[$key][] = 'regex:~\A/(?![/\\\\])[^\s\\\\]*\z~D';
            }
        }
        foreach (['contact_website', 'contact_map_url'] as $key) {
            $rules[$key][] = 'url:https';
            $rules[$key][] = 'regex:~\Ahttps://[a-zA-Z0-9.-]+(?::[0-9]{1,5})?(?:[/?#][^\s<>"\\\\]*)?\z~D';
        }
        $rules['contact_email'][] = 'email';
        $rules['contact_secondary_phone'][] = 'regex:/\A\+?[0-9]{7,15}\z/D';
        foreach (\App\Modules\Website\Application\ThemeService::EXTRA_COLORS as $key => $value) {
            $rules[$key] = ['sometimes', 'required', 'string', 'regex:/\A#[0-9a-fA-F]{6}\z/D'];
        }
        foreach (\App\Modules\Website\Application\SiteContent::SECTIONS as $key => $label) {
            $rules['show_'.$key] = ['sometimes', 'required', 'boolean'];
        }
        $rules['section_order'] = ['sometimes', 'array', 'size:6'];
        $rules['section_order.*'] = ['required', 'distinct', \Illuminate\Validation\Rule::in(array_keys(\App\Modules\Website\Application\SiteContent::SECTIONS))];
        foreach (['highlight_items', 'why_items', 'faq_items', 'footer_features', 'home_about_features', 'home_about_stats', 'home_services_highlights'] as $key) {
            $maximum = match ($key) {
                'footer_features', 'home_about_stats' => 3, 'home_about_features', 'home_services_highlights' => 4, default => 8
            };
            $rules[$key] = ['sometimes', 'array', 'max:'.$maximum];
            $rules[$key.'.*'] = ['array:title,text'];
            $rules[$key.'.*.title'] = ['required', 'string', 'max:180'];
            $rules[$key.'.*.text'] = ['required', 'string', 'max:1000'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $themes = app(\App\Modules\Website\Application\ThemeService::class);
            $colors = array_replace($themes->palette(), $this->only(array_keys(\App\Modules\Website\Application\ThemeService::DEFAULTS + \App\Modules\Website\Application\ThemeService::EXTRA_COLORS)));
            foreach (\App\Modules\Website\Application\ThemeService::invalidPaletteFields($colors) as $field) {
                $validator->errors()->add($field, 'کنتراست رنگ متن و زمینه باید حداقل ۴٫۵ باشد؛ رنگ تیره‌تری برای متن و دکمه یا زمینه روشن‌تری انتخاب کنید.');
            }

        }];
    }
}
