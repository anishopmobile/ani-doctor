<?php

namespace Database\Seeders;

use App\Modules\Identity\Infrastructure\Permission;
use App\Modules\Identity\Infrastructure\Role;
use Illuminate\Database\Seeder;

final class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['patient' => ['panel.patient'], 'secretary' => ['panel.secretary', 'schedules.manage', 'appointments.manage', 'appointments.override_capacity', 'appointments.override_window'], 'admin' => ['panel.admin', 'catalog.manage', 'schedules.manage', 'schedules.settings', 'appointments.manage', 'appointments.override_capacity', 'appointments.override_window', 'booking.settings']] as $name => $permissions) {
            $permissions = array_merge($permissions, match ($name) {
                'secretary' => ['payments.collect'],
                'admin' => ['payments.settings', 'payments.review', 'refunds.manage'],
                default => [],
            });
            if (in_array($name, ['secretary', 'admin'], true)) {
                $permissions = array_merge($permissions, ['desk.view', 'desk.book', 'appointments.urgent', 'attendance.manage', 'patients.manage']);
            }
            $role = Role::firstOrCreate(['name' => $name]);
            $role->permissions()->sync(array_map(fn ($permission) => Permission::firstOrCreate(['name' => $permission])->id, $permissions));
        }
    }
}
