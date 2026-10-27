<?php

namespace App\Modules\Website\Application;

use App\Modules\Website\Infrastructure\WebsiteServiceContent;

final class ServiceContentService
{
    public function save(int $serviceId, array $data): WebsiteServiceContent
    {
        return WebsiteServiceContent::query()->updateOrCreate(
            ['service_id' => $serviceId],
            array_filter([
                'summary' => $data['summary'] ?? null,
                'description' => $data['description'] ?? null,
                'is_published' => (bool) $data['is_published'],
                'sort_order' => $data['sort_order'] ?? null,
            ], fn ($value, $key) => $key !== 'sort_order' || $value !== null, ARRAY_FILTER_USE_BOTH)
        );
    }

    public function catalogQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return \App\Modules\Catalog\Infrastructure\Service::query()
            ->where('is_active', true)->where('is_online', true)
            ->with(['media', 'websiteContent' => fn ($query) => $query->where('is_published', true)])
            ->orderByRaw('COALESCE((SELECT sort_order FROM website_service_contents WHERE service_id = services.id AND is_published = 1), 0) ASC')
            ->orderBy('name');
    }

    public function published(int $serviceId): ?WebsiteServiceContent
    {
        return WebsiteServiceContent::query()
            ->where('service_id', $serviceId)
            ->where('is_published', true)
            ->first();
    }
}
