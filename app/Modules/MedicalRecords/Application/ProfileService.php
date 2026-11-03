<?php

namespace App\Modules\MedicalRecords\Application;

use App\Http\Requests\MedicalProfileRequest;
use App\Modules\Identity\Infrastructure\User;
use App\Modules\MedicalRecords\Infrastructure\MedicalProfile;
use App\Modules\Patients\Infrastructure\Patient;
use App\Modules\Scheduling\Application\JalaliDate;
use App\Policies\MedicalRecordPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProfileService
{
    public function identityHash(string $value): string
    {
        return hash_hmac('sha256', 'emr-identity:'.MedicalProfileRequest::normalize($value), config('app.key'));
    }

    public function search(array $filter)
    {
        $query = MedicalProfileRequest::normalize($filter['query'] ?? '');
        $patients = Patient::query()->when($query !== '', function ($patients) use ($query): void {
            // A known human file number is an exact identifier. Preserve its unique-index lookup
            // instead of combining it with the deliberately broader contains-name scan.
            if (Patient::where('file_number', $query)->exists()) {
                $patients->where('file_number', $query);
                return;
            }
            $patients->where(function ($patients) use ($query): void {
                $like = '%'.strtr($query, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
                $patients->whereRaw("REPLACE(REPLACE(name, 'ي', 'ی'), 'ك', 'ک') LIKE ? ESCAPE '!'", [$like])
                    ->orWhere('contact_mobile', $query)->orWhere('file_number', $query)
                    ->orWhereIn('id', MedicalProfile::where('identity_hash', $this->identityHash($query))->select('patient_id'));
            });
        });
        if (!empty($filter['status'])) {
            $patients->whereIn('id', MedicalProfile::where('status', $filter['status'])->select('patient_id'));
        }
        foreach (['from' => '>=', 'to' => '<='] as $field => $operator) {
            if (!empty($filter[$field])) {
                $date = app(JalaliDate::class)->parse(\App\Modules\Identity\Domain\MobileNumber::normalize($filter[$field]));
                $patients->where('created_at', $operator, ($field === 'to' ? $date->endOfDay() : $date)->utc());
            }
        }
        return match ($filter['sort'] ?? 'newest') {
            'oldest' => $patients->orderBy('id'),
            'name' => $patients->orderBy('name')->orderBy('id'),
            'file' => $patients->orderBy('file_number')->orderBy('id'),
            default => $patients->orderByDesc('id'),
        };
    }

    public function save(User $actor, array $data, ?Patient $patient): Patient
    {
        abort_unless(app(MedicalRecordPolicy::class)->administrative($actor), 403);
        return DB::transaction(function () use ($actor, $data, $patient): Patient {
            $patient = $patient ? Patient::whereKey($patient->id)->lockForUpdate()->firstOrFail() : new Patient();
            $profile = $patient->exists ? MedicalProfile::where('patient_id', $patient->id)->lockForUpdate()->first() : null;
            abort_unless(($profile?->version ?? 0) === (int) $data['expected_version'], 409, 'اطلاعات تغییر کرده؛ صفحه را تازه کنید.');
            $hash = !empty($data['identity_number']) ? $this->identityHash($data['identity_number']) : null;
            $similar = Patient::where('id', '!=', $patient->id ?? 0)->where(function ($query) use ($data, $hash): void {
                $query->where('name', $data['name']);
                if (!empty($data['contact_mobile'])) {
                    $query->orWhere('contact_mobile', $data['contact_mobile']);
                }
                if ($hash) {
                    $query->orWhereIn('id', MedicalProfile::where('identity_hash', $hash)->select('patient_id'));
                }
            })->orderBy('id')->limit(11)->get(['id', 'name', 'contact_mobile', 'file_number', 'updated_at']);
            $reviewData = array_diff_key($data, array_flip(['duplicate_ack', 'duplicate_token']));
            $fingerprint = hash_hmac('sha256', json_encode([$actor->id, $patient->id, $reviewData, $similar->toArray()], JSON_THROW_ON_ERROR), config('app.key'));
            $reviewed = false;
            try {
                [$issued, $confirmed] = explode(':', \Illuminate\Support\Facades\Crypt::decryptString($data['duplicate_token'] ?? ''), 2);
                $reviewed = ctype_digit($issued) && (int) $issued <= time() && time() - (int) $issued <= 1800 && hash_equals($fingerprint, $confirmed);
            } catch (\Throwable) {
                // Missing, expired or modified tokens never authorize a duplicate.
            }
            if ($similar->isNotEmpty() && (empty($data['duplicate_ack']) || !$reviewed)) {
                session()->flash('emr.duplicate_review', ['patient_id' => $patient->id, 'patients' => $similar->take(10)->toArray(), 'more' => $similar->count() > 10, 'token' => \Illuminate\Support\Facades\Crypt::encryptString(time().':'.$fingerprint)]);
                throw ValidationException::withMessages(['duplicate_ack' => 'پرونده‌ای با نام، تماس یا شناسه مشابه وجود دارد. جستجو کنید و فقط پس از بررسی انسانی ادامه دهید.']);
            }
            $patient->fill(['name' => $data['name'], 'contact_mobile' => $data['contact_mobile'] ?? null])->save();
            $this->ensureFileNumber($patient);
            $identity = [...array_intersect_key($profile?->identity ?? [], ['enabled_sections' => true]), ...array_intersect_key($data, array_flip(['identity_type', 'identity_number', 'marital_status', 'address', 'administrative_note']))];
            $identity['birth_date'] = !empty($data['birth_jalali']) ? app(JalaliDate::class)->parse(\App\Modules\Identity\Domain\MobileNumber::normalize($data['birth_jalali']))->format('Y-m-d') : null;
            $profile ??= new MedicalProfile(['patient_id' => $patient->id, 'created_by' => $actor->id, 'version' => 0]);
            $profile->forceFill(['identity' => $identity, 'identity_hash' => $hash, 'status' => $data['status'], 'updated_by' => $actor->id, 'version' => $profile->version + 1])->save();
            app(MedicalAudit::class)->record($actor->id, 'profile.saved', $patient->id);
            return $patient;
        }, 3);
    }

    /** Caller holds the patient row lock, or has just inserted this patient in its transaction. */
    public function ensureFileNumber(Patient $patient): void
    {
        $patient->refresh();
        if (!$patient->file_number) {
            $number = 'EMR-'.$patient->id;
            while (Patient::where('file_number', $number)->where('id', '!=', $patient->id)->exists()) {
                $number .= '-'.strtoupper(bin2hex(random_bytes(2)));
            }
            $patient->file_number = $number;
            $patient->save();
        }
    }
}
