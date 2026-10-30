<?php

namespace App\Modules\Website\Application;

use App\Modules\Website\Infrastructure\WebsiteMedia;
use App\Modules\Catalog\Infrastructure\Service;
use Illuminate\Support\Facades\Storage;

final class HomeSectionMediaService
{
    private function available(?WebsiteMedia $image): bool
    {
        return $image && preg_match('/\Awebsite-media\/[a-zA-Z0-9_-]+\.(?:png|jpe?g|webp)\z/D', $image->path)
            && in_array($image->mime_type, ['image/png', 'image/jpeg', 'image/webp'], true) && Storage::disk('local')->exists($image->path);
    }

    public function image(array $theme, string $slot): array
    {
        if (!in_array($slot, ['about', 'services', 'contact'], true)) {
            throw new \InvalidArgumentException('Unsupported home media slot');
        }
        $types = array_values(array_filter([$slot, 'portrait'], fn ($type) => !empty($theme[$type.'_is_published'])));
        $media = WebsiteMedia::query()->where('owner_id', 1)->whereIn('owner_type', $types)->get();
        foreach ($types as $type) {
            $image = $media->firstWhere('owner_type', $type);
            if ($this->available($image)) {
                return ['url' => route('website.media.show', $image), 'alt' => $image->alt_text ?: $theme['brand_name'], 'custom' => true];
            }
        }
        return ['url' => asset('assets/clinic-art.webp'), 'alt' => $theme['brand_name'].' — '.$theme['brand_subtitle'], 'custom' => false];
    }

    public function cardImage(Service $service): ?array
    {
        if ($service->websiteContent?->is_published && $this->available($service->media)) {
            return ['url' => route('website.media.show', $service->media), 'alt' => $service->media->alt_text ?: $service->name];
        }
        return null;
    }
}
