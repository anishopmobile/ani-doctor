<?php

namespace App\Modules\Billing\Application;

use Illuminate\Support\Facades\DB;

final class FinancialAudit
{
    public function record(string $action, ?int $actor, string $entity, string $id, array $before, array $after, string $reason): void
    {
        DB::table('financial_events')->insert(['actor_id' => $actor, 'action' => $action, 'entity_type' => $entity, 'entity_id' => $id, 'before_state' => json_encode($before), 'after_state' => json_encode($after), 'reason' => $reason, 'created_at' => now()]);
    }
}
