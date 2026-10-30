<?php

namespace App\Policies;

use App\Modules\Identity\Infrastructure\User;

final class MedicalRecordPolicy
{
    public function administrative(User $user): bool
    {
        return $user->hasPermission('patients.manage') && ($this->clinical($user)
            || ($user->hasPermission('panel.secretary') && $user->roles()->where('name', 'secretary')->exists()));
    }

    public function clinical(User $user): bool
    {
        return $user->hasPermission('panel.admin') && $user->roles()->where('name', 'admin')->exists();
    }
}
