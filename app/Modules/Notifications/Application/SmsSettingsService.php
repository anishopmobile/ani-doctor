<?php

namespace App\Modules\Notifications\Application;

use App\Modules\Identity\Infrastructure\User;
use App\Modules\SettingsAudit\Application\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SmsSettingsService
{
    public function save(User $actor, array $data): void
    {
        abort_unless($actor->hasPermission('notifications.manage'), 403);
        DB::transaction(function () use ($actor, $data): void {
            if ($data['event'] === 'secretary' && !User::find($data['secretary_id'])?->hasPermission('panel.secretary')) {
                throw ValidationException::withMessages(['secretary_id' => 'دریافت‌کننده باید منشی فعال و احرازشده باشد.']);
            }
            $before = DB::table('sms_templates')->where('event', $data['event'])->lockForUpdate()->first();
            $after = ['provider_template_id' => $data['provider_template_id'], 'enabled' => $data['enabled'], 'secretary_id' => $data['event'] === 'secretary' ? $data['secretary_id'] : null, 'reminder_minutes' => $data['reminder_minutes'], 'updated_at' => now()];
            DB::table('sms_templates')->updateOrInsert(['event' => $data['event']], [...$after, 'created_at' => $before?->created_at ?? now()]);
            app(AuditService::class)->record($actor->id, 'sms.template.'.$data['event'], $before ? (array) $before : null, [...$after, 'reason' => $data['reason']]);
        }, 3);
    }
}
