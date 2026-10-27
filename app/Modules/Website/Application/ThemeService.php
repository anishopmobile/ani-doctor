<?php

namespace App\Modules\Website\Application;

use App\Modules\Website\Infrastructure\WebsiteTheme;

final class ThemeService
{
    public const DEFAULTS = [
        'primary_color' => '#ce4081',
        'text_color' => '#362147',
        'surface_color' => '#fffafc',
    ];

    public const BRAND_DEFAULTS = [
        'brand_name' => 'دکتر زهرا باقری',
        'brand_subtitle' => 'متخصص زنان، زایمان و نازایی',
        'contact_phone' => '09172551637',
        'contact_address' => 'استان فارس، شهرستان لامرد، بلوار شاهد، ساختمان دی، طبقه سوم، واحد ۵',
        'seo_description' => 'معرفی خدمات قابل رزرو مطب دکتر زهرا باقری',
    ];

    public function settings(): array
    {
        $theme = WebsiteTheme::query()->whereKey(1)->first();

        return array_replace(
            self::DEFAULTS,
            $theme?->only(array_keys(self::DEFAULTS)) ?? [],
            self::BRAND_DEFAULTS,
            $theme?->brand_settings ?? []
        );
    }

    public function save(array $values): void
    {
        $colors = array_intersect_key($values, self::DEFAULTS);
        $brand = array_intersect_key($values, self::BRAND_DEFAULTS);
        $theme = WebsiteTheme::query()->firstOrNew(['id' => 1]);
        $theme->fill($colors);
        if ($brand !== []) {
            $theme->brand_settings = array_replace($theme->brand_settings ?? [], $brand);
        }
        $theme->save();
    }
}
