<?php

namespace App\Modules\Website\Application;

use App\Modules\Website\Infrastructure\WebsiteTheme;

final class ThemeService
{
    public const DEFAULTS = [
        'primary_color' => '#087b80',
        'text_color' => '#20344d',
        'surface_color' => '#ffffff',
    ];

    public const EXTRA_COLORS = ['primary_hover_color' => '#06686c', 'secondary_color' => '#204d93', 'accent_color' => '#a64b1f', 'background_color' => '#f5f7fa'];

    public const BRAND_DEFAULTS = [
        'brand_name' => 'دکتر زهرا باقری',
        'brand_subtitle' => 'متخصص زنان، زایمان و نازایی',
        'contact_phone' => '09172551637',
        'contact_address' => 'استان فارس، شهرستان لامرد، بلوار شاهد، ساختمان دی، طبقه سوم، واحد ۵',
        'seo_description' => 'معرفی خدمات قابل رزرو مطب دکتر زهرا باقری',
        'logo_is_published' => false,
        'portrait_is_published' => false,
        'favicon_is_published' => false,
    ];

    public function settings(): array
    {
        $theme = WebsiteTheme::query()->whereKey(1)->first();

        $values = array_replace(
            self::DEFAULTS,
            $theme?->only(array_keys(self::DEFAULTS)) ?? [],
            self::BRAND_DEFAULTS,
            self::EXTRA_COLORS,
            SiteContent::defaults(),
            $theme?->brand_settings ?? []
        );
        $values['settings_version'] = (int) ($theme?->brand_settings['_version'] ?? 0);

        return SiteContent::sanitize($values);
    }

    public function save(array $values): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($values): void {
            $theme = WebsiteTheme::query()->whereKey(1)->lockForUpdate()->first() ?? new WebsiteTheme();
            $current = $theme->brand_settings ?? [];
            if (isset($values['expected_settings_version']) && (int) $values['expected_settings_version'] !== (int) ($current['_version'] ?? 0)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['expected_settings_version' => 'تنظیمات در پنجره دیگری تغییر کرده است؛ صفحه را تازه کنید و دوباره بررسی کنید.']);
            }
            $brand = array_intersect_key($values, self::BRAND_DEFAULTS + self::EXTRA_COLORS + SiteContent::defaults());
            foreach (array_keys($brand) as $key) {
                if (str_starts_with($key, 'show_') || str_ends_with($key, '_is_published')) {
                    $brand[$key] = (bool) $brand[$key];
                }
            }
            $brand['_version'] = (int) ($current['_version'] ?? 0) + 1;
            $theme->forceFill(['id' => 1]);
            if (!$theme->exists) {
                $theme->fill(self::DEFAULTS);
            }
            $theme->fill(array_intersect_key($values, self::DEFAULTS));
            $theme->brand_settings = array_replace($current, $brand);
            $theme->save();
        }, 3);
    }

    public function publishedLogo(): ?\App\Modules\Website\Infrastructure\WebsiteMedia
    {
        return $this->publishedMedia('brand');
    }

    public function publishedMedia(string $type): ?\App\Modules\Website\Infrastructure\WebsiteMedia
    {
        $flag = match ($type) {
            'brand' => 'logo_is_published', 'portrait' => 'portrait_is_published', 'favicon' => 'favicon_is_published', default => null
        };
        if (!$flag || ($this->settings()[$flag] ?? false) !== true) {
            return null;
        }

        return \App\Modules\Website\Infrastructure\WebsiteMedia::query()->where('owner_type', $type)->where('owner_id', 1)->first();
    }

    public function palette(?array $settings = null): array
    {
        $fallback = self::DEFAULTS + self::EXTRA_COLORS;
        $values = array_intersect_key($settings ?? $this->settings(), $fallback);
        if (self::invalidPaletteFields($values) !== []) {
            return $fallback;
        }

        return $values;
    }

    public static function invalidPaletteFields(array $values): array
    {
        $invalid = [];
        foreach (array_keys(self::DEFAULTS + self::EXTRA_COLORS) as $key) {
            if (!is_string($values[$key] ?? null) || !preg_match('/\A#[0-9a-fA-F]{6}\z/D', $values[$key])) {
                $invalid[] = $key;
            }
        }
        if ($invalid !== []) {
            return $invalid;
        }
        foreach (['primary_color', 'text_color', 'primary_hover_color', 'secondary_color', 'accent_color'] as $key) {
            foreach (['#ffffff', $values['surface_color'], $values['background_color']] as $background) {
                if (self::contrast($values[$key], $background) < 4.5) {
                    $invalid[] = $key;
                }
            }
        }
        // Fixed semantic/help text must also remain readable on editable surfaces.
        foreach (['surface_color', 'background_color'] as $key) {
            foreach (['#52657a', '#237355', '#956019', '#b33b4c', '#204d93'] as $foreground) {
                if (self::contrast($foreground, $values[$key]) < 4.5) {
                    $invalid[] = $key;
                }
            }
        }
        return array_values(array_unique($invalid));
    }

    public function css(?array $settings = null): string
    {
        $palette = $this->palette($settings);
        $names = ['primary_color' => 'brand-primary', 'text_color' => 'text-primary', 'surface_color' => 'surface', 'primary_hover_color' => 'brand-hover', 'secondary_color' => 'brand-secondary', 'accent_color' => 'brand-accent', 'background_color' => 'page-bg'];
        $css = ':root { --pink: '.$palette['primary_color'].'; --purple: '.$palette['text_color'].'; --paper: '.$palette['surface_color'].'; ';
        foreach ($names as $key => $name) {
            $css .= '--'.$name.': '.$palette[$key].'; ';
        }

        return $css.'}';
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
