<?php

namespace App\Modules\Website\Application;

use App\Modules\Website\Infrastructure\WebsiteTheme;

final class ThemeService
{
    public const DEFAULTS = [
        'primary_color' => '#c83c7b',
        'text_color' => '#362147',
        'surface_color' => '#fffafc',
    ];

    public const BRAND_DEFAULTS = [
        'brand_name' => 'دکتر زهرا باقری',
        'brand_subtitle' => 'متخصص زنان، زایمان و نازایی',
        'contact_phone' => '09172551637',
        'contact_address' => 'استان فارس، شهرستان لامرد، بلوار شاهد، ساختمان دی، طبقه سوم، واحد ۵',
        'seo_description' => 'معرفی خدمات قابل رزرو مطب دکتر زهرا باقری',
        'logo_is_published' => false,
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
        if (array_key_exists('logo_is_published', $brand)) {
            $brand['logo_is_published'] = (bool) $brand['logo_is_published'];
        }
        $theme = WebsiteTheme::query()->firstOrNew(['id' => 1]);
        // MySQL AUTO_INCREMENT is not rewound by transaction rollback.
        $theme->forceFill(['id' => 1]);
        $theme->fill($colors);
        if ($brand !== []) {
            $theme->brand_settings = array_replace($theme->brand_settings ?? [], $brand);
        }
        $theme->save();
    }

    public function publishedLogo(): ?\App\Modules\Website\Infrastructure\WebsiteMedia
    {
        $theme = WebsiteTheme::query()->with('media')->find(1);

        return ($theme?->brand_settings['logo_is_published'] ?? false) === true ? $theme->media : null;
    }

    public function palette(): array
    {
        $values = array_intersect_key($this->settings(), self::DEFAULTS);
        foreach ($values as $key => $value) {
            if (!is_string($value) || !preg_match('/\A#[0-9a-fA-F]{6}\z/D', $value)) {
                return self::DEFAULTS;
            }
        }
        foreach (['primary_color', 'text_color'] as $key) {
            if (self::contrast($values[$key], '#ffffff') < 4.5 || self::contrast($values[$key], $values['surface_color']) < 4.5) {
                return self::DEFAULTS;
            }
        }

        return $values;
    }

    public static function contrast(string $first, string $second): float
    {
        $luminance = static function (string $hex): float {
            $rgb = array_map(fn ($channel) => hexdec($channel) / 255, str_split(substr($hex, 1), 2));
            $rgb = array_map(fn ($channel) => $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4, $rgb);

            return 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];
        };
        $a = $luminance($first);
        $b = $luminance($second);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }
}
