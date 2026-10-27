<?php

namespace App\Modules\Catalog\Application;

use App\Modules\Catalog\Infrastructure\Service;
use Illuminate\Support\Facades\DB;

final class CatalogService
{
    public function __construct(private readonly \App\Modules\SettingsAudit\Application\AuditService $audit)
    {
    }

    public function save(array $data, int $actor, ?Service $service = null): Service
    {
        return DB::transaction(function () use ($data, $actor, $service): Service {
            $service = $service ? Service::whereKey($service->id)->lockForUpdate()->firstOrFail() : new Service();
            $before = $service->exists ? $service->toArray() : null;
            $priceChanged = !$service->exists || $service->price_irr !== (int) $data['price_irr'];
            $service->fill($data)->save();
            if ($priceChanged) {
                DB::table('service_price_histories')->insert(['service_id' => $service->id, 'price_irr' => $service->price_irr, 'actor_id' => $actor, 'created_at' => now()]);
            }
            $this->audit->record($actor, 'service.saved', $before, $service->toArray());
            return $service;
        });
    }

    public function archive(Service $service, int $actor): void
    {
        DB::transaction(function () use ($service, $actor): void {
            $service = Service::whereKey($service->id)->lockForUpdate()->firstOrFail();
            $this->audit->record($actor, 'service.archived', $service->toArray(), null);
            $service->delete();
            DB::afterCommit(function () use ($service): void {
                $service->media?->deleteWithFile();
            });
        });
    }
}
