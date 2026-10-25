<?php

namespace Database\Seeders;

use App\Modules\Identity\Infrastructure\Permission;
use App\Modules\Identity\Infrastructure\Role;
use Illuminate\Database\Seeder;

final class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['patient' => ['panel.patient'], 'secretary' => ['panel.secretary', 'schedules.manage'], 'admin' => ['panel.admin', 'catalog.manage', 'schedules.manage', 'schedules.settings']] as $name => $permissions) {
            $role = Role::firstOrCreate(['name' => $name]);
            $role->permissions()->sync(array_map(fn ($permission) => Permission::firstOrCreate(['name' => $permission])->id, $permissions));
        }
    }
}
