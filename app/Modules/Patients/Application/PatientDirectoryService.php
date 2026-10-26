<?php

namespace App\Modules\Patients\Application;

use App\Modules\Identity\Domain\BookingActor;
use App\Modules\Patients\Infrastructure\Patient;
use App\Modules\SettingsAudit\Application\AuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class PatientDirectoryService
{
    public function search(BookingActor $actor, string $query)
    {
        $this->authorize($actor);
        return Patient::query()->when($query !== '', fn ($patients) => $patients->where(function ($patients) use ($query): void {
            $like = '%'.strtr($query, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $patients->whereRaw("name LIKE ? ESCAPE '!'", [$like])->orWhere('contact_mobile', $query)->orWhere('file_number', $query);
        }))->orderBy('name')->orderBy('id');
    }
    public function save(BookingActor $actor, array $data, ?Patient $patient = null): Patient
    {
        $this->authorize($actor);
        return DB::transaction(function () use ($actor, $data, $patient): Patient {
            $patient = $patient ? Patient::whereKey($patient->id)->lockForUpdate()->firstOrFail() : new Patient();
            $before = $patient->exists ? $patient->only(['name', 'contact_mobile', 'file_number']) : null;
            $patient->fill($data)->save();
            app(AuditService::class)->record($actor->userId, 'patient.saved', $before, ['patient_id' => $patient->id, ...$patient->only(['name', 'contact_mobile', 'file_number'])]);
            return $patient;
        }, 3);
    }
    private function authorize(BookingActor $actor): void
    {
        if (!$actor->userId || !$actor->staff() || !$actor->can('patients.manage')) {
            throw new AuthorizationException();
        }
    }
}
