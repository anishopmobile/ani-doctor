<?php

namespace App\Modules\Patients\Application;

use App\Modules\Identity\Domain\BookingActor;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Modules\Patients\Infrastructure\Patient;
use Illuminate\Support\Facades\DB;

final class BookingPatientService
{
    public function choices(BookingActor $actor)
    {
        $query = Patient::query();
        if (!$actor->staff()) {
            $query->whereIn('id', $actor->userId ? DB::table('patient_user_links')->where('user_id', $actor->userId)->select('patient_id') : DB::table('guest_patient_links')->where('mobile_hash', $actor->mobileHash)->select('patient_id'));
        }
        return $query->orderBy('name')->limit(100)->get(['id','name']);
    }
    public function resolve(BookingActor $actor, array $data, string $ownerHash): Patient
    {
        if (!empty($data['patient_id'])) {
            $patient = Patient::whereKey($data['patient_id'])->lockForUpdate()->first();
            $linked = $actor->userId ? DB::table('patient_user_links')->where('user_id', $actor->userId)->where('patient_id', $data['patient_id'])->exists() : DB::table('guest_patient_links')->where('mobile_hash', $actor->mobileHash)->where('patient_id', $data['patient_id'])->exists();
            if (!$patient || (!$actor->staff() && !$linked)) {
                throw (new ModelNotFoundException())->setModel(Patient::class);
            }
            return $patient;
        }
        $patient = Patient::create(['name' => $data['patient_name'],'contact_mobile' => $data['contact_mobile'] ?? ($actor->staff() ? $data['booker_mobile'] : $actor->mobile)]);
        if ($actor->userId && !$actor->staff()) {
            DB::table('patient_user_links')->insert(['user_id' => $actor->userId,'patient_id' => $patient->id,'created_at' => now()]);
        } else {
            DB::table('guest_patient_links')->insert(['mobile_hash' => $ownerHash,'patient_id' => $patient->id]);
        }
        return $patient;
    }
    public function claim(int $patientId, int $userId): void
    {
        DB::table('patient_user_links')->insertOrIgnore(['patient_id' => $patientId,'user_id' => $userId,'created_at' => now()]);
    }
}
