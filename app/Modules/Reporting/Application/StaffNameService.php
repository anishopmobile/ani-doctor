<?php

namespace App\Modules\Reporting\Application;

use App\Modules\Identity\Infrastructure\User;
use App\Modules\SettingsAudit\Application\AuditService;
use Illuminate\Support\Facades\DB;

final class StaffNameService
{
    public function save(User $actor, int $id, string $name, string $reason): void
    {
        abort_unless($actor->hasPermission('reports.finance'), 403);
        DB::transaction(function () use ($actor, $id, $name, $reason): void {
            $user = User::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($user->roles()->whereIn('name', ['secretary', 'admin'])->exists(), 422, 'حساب انتخابی اپراتور مطب نیست.');
            app(AuditService::class)->record($actor->id, 'report.staff_name', ['user_id' => $id, 'name' => $user->display_name], ['user_id' => $id, 'name' => $name, 'reason' => $reason]);
            $user->forceFill(['display_name' => $name])->save();
        }, 3);
    }
}
