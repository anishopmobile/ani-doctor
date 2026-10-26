<?php

namespace App\Modules\SettingsAudit\Application;

use Illuminate\Support\Facades\DB;

final class AuditService
{
    public function record(int $actor, string $action, ?array $before, ?array $after): void
    {
        DB::table('configuration_audit_events')->insert(['actor_id' => $actor, 'action' => $action, 'before_state' => json_encode($before), 'after_state' => json_encode($after), 'created_at' => now()]);
    }
}
