<?php

namespace App\Modules\Website\Application;

use App\Modules\Website\Infrastructure\WebsiteServiceContent;

final class ServiceContentService
{
    public function save(int $serviceId, array $data): WebsiteServiceContent
    {
        return WebsiteServiceContent::query()->updateOrCreate(
            ['service_id' => $serviceId],
            [
                'summary' => $data['summary'] ?? null,
                'description' => $data['description'] ?? null,
                'is_published' => (bool) $data['is_published'],
            ]
        );
    }

    public function published(int $serviceId): ?WebsiteServiceContent
    {
        return WebsiteServiceContent::query()
            ->where('service_id', $serviceId)
            ->where('is_published', true)
            ->first();
    }
}
