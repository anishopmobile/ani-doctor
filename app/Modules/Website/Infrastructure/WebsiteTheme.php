<?php

namespace App\Modules\Website\Infrastructure;

use Illuminate\Database\Eloquent\Model;

final class WebsiteTheme extends Model
{
    protected $table = 'website_theme_settings';

    protected $fillable = ['primary_color', 'text_color', 'surface_color', 'brand_settings'];

    public function media(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(WebsiteMedia::class, 'owner_id')->where('owner_type', 'brand');
    }

    protected function casts(): array
    {
        return ['brand_settings' => 'array'];
    }
}
