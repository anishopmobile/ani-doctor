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

    public function settings(): array
    {
        return array_replace(self::DEFAULTS, WebsiteTheme::query()->whereKey(1)->first()?->only(array_keys(self::DEFAULTS)) ?? []);
    }

    public function save(array $values): void
    {
        WebsiteTheme::query()->updateOrCreate(['id' => 1], $values);
    }
}
