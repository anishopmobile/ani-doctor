<?php

namespace App\Modules\Website\Infrastructure;

use Illuminate\Database\Eloquent\Model;

final class WebsiteTheme extends Model
{
    protected $table = 'website_theme_settings';

    protected $fillable = ['primary_color', 'text_color', 'surface_color', 'brand_settings'];

    protected function casts(): array
    {
        return ['brand_settings' => 'array'];
    }
}
