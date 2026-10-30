<?php

namespace App\Modules\Website\Application;

use App\Modules\Website\Infrastructure\WebsiteMedia;
use Illuminate\Support\Facades\Storage;

final class HomeAboutService
{
    public function image(array $theme): array
    {
        $types = array_values(array_filter(['about', 'portrait'], fn ($type) => !empty($theme[$type.'_is_published'])));
        $media = WebsiteMedia::query()->where('owner_id', 1)->whereIn('owner_type', $types)->get();
        foreach ($types as $type) {
            $image = $media->firstWhere('owner_type', $type);
            if ($image && preg_match('/\Awebsite-media\/[a-zA-Z0-9_-]+\.(?:png|jpe?g|webp)\z/D', $image->path)
                && in_array($image->mime_type, ['image/png', 'image/jpeg', 'image/webp'], true) && Storage::disk('local')->exists($image->path)) {
                return ['url' => route('website.media.show', $image), 'alt' => $image->alt_text ?: $theme['brand_name'], 'custom' => true];
            }
        }
        return ['url' => asset('assets/clinic-art.webp'), 'alt' => $theme['brand_name'].' — '.$theme['brand_subtitle'], 'custom' => false];
    }
}
