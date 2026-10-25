<?php

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\MobileNumber;
use App\Modules\Identity\Infrastructure\Role;
use App\Modules\Identity\Infrastructure\User;
use Illuminate\Support\Facades\DB;

final class StaffService
{
    /** Only called by the trusted operator's Artisan command, never an HTTP route. */
    public function provision(string $mobile, string $role): void
    {
        if (!MobileNumber::isValid($mobile) || !in_array($role, ['admin', 'secretary'], true)) {
            throw new \InvalidArgumentException('Invalid mobile or staff role');
        }
        DB::transaction(function () use ($mobile, $role): void {
            $user = User::firstOrCreate(['mobile' => $mobile]);
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->roles()->syncWithoutDetaching([Role::where('name', $role)->sole()->id]);
            DB::table('identity_audit_events')->insert(['user_id' => $user->id, 'event' => 'staff_role_granted_by_cli', 'role' => $role, 'created_at' => now()]);
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });
    }
}
