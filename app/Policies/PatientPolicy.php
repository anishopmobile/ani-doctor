<?php

namespace App\Policies;

use App\Modules\Identity\Infrastructure\User;
use App\Modules\Patients\Infrastructure\Patient;
use Illuminate\Support\Facades\DB;

final class PatientPolicy
{
    public function view(User $user, Patient $patient): bool
    {
        return $user->is_active && $user->mobile_verified_at !== null
            && DB::table('patient_user_links')->where('user_id', $user->id)->where('patient_id', $patient->id)->exists();
    }
}
