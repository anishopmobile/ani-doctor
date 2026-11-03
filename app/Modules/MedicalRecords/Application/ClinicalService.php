<?php

namespace App\Modules\MedicalRecords\Application;

use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Identity\Infrastructure\User;
use App\Modules\MedicalRecords\Infrastructure\ClinicalRecord;
use App\Modules\Patients\Infrastructure\Patient;
use App\Modules\Scheduling\Application\JalaliDate;
use App\Policies\MedicalRecordPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClinicalService
{
    public function save(User $actor, Patient $patient, string $kind, array $data, ?ClinicalRecord $record = null): ClinicalRecord
    {
        abort_unless(app(MedicalRecordPolicy::class)->clinical($actor), 403);
        abort_unless(array_key_exists($kind, ClinicalSchema::labels()), 404);
        return DB::transaction(function () use ($actor, $patient, $kind, $data, $record): ClinicalRecord {
            Patient::whereKey($patient->id)->lockForUpdate()->firstOrFail();
            app(ProfileService::class)->ensureFileNumber($patient);
            $requestHash = hash_hmac('sha256', json_encode([$patient->id, $kind, $data], JSON_THROW_ON_ERROR), config('app.key'));
            if (!$record && ($existing = ClinicalRecord::where('request_key', $data['request_key'])->first())) {
                abort_unless(hash_equals($existing->request_hash, $requestHash), 409, 'درخواست تکراری با محتوای متفاوت است.');
                return $existing;
            }
            $parent = null;
            if ($record) {
                $record = ClinicalRecord::where('patient_id', $patient->id)->whereKey($record->id)->lockForUpdate()->firstOrFail();
                abort_unless($record->kind === $kind && $record->status === 'draft', 409, 'متن نهایی قابل بازنویسی نیست؛ الحاقیه ثبت کنید.');
                abort_unless($record->version === (int) ($data['expected_version'] ?? 0), 409, 'نسخه تغییر کرده؛ صفحه را تازه کنید.');
                // Links are fixed when a draft is created, preventing silent reassignment.
            } else {
                $parent = !empty($data['parent_id']) ? ClinicalRecord::where('patient_id', $patient->id)->whereKey($data['parent_id'])->lockForUpdate()->firstOrFail() : null;
                if ($parent) {
                    abort_unless($parent->kind === $kind && $parent->status === 'final' && !ClinicalRecord::where('parent_id', $parent->id)->exists(), 409, 'آخرین نسخه نهایی را اصلاح کنید.');
                    if (empty($data['reason'])) {
                        throw ValidationException::withMessages(['reason' => 'علت اصلاح لازم است.']);
                    }
                }
                $appointment = null;
                if (!$parent && !empty($data['appointment_id'])) {
                    abort_unless($kind === 'visit', 422);
                    $appointment = Appointment::where('patient_id', $patient->id)->whereKey($data['appointment_id'])->lockForUpdate()->firstOrFail();
                    abort_unless(in_array($appointment->status, ['confirmed', 'completed'], true) && $appointment->arrived_at !== null && $appointment->no_show_at === null, 422, 'شروع ویزیت نیازمند حضور ثبت‌شده و نوبت معتبر است.');
                    if ($existing = ClinicalRecord::where('appointment_id', $appointment->id)->first()) {
                        return $existing;
                    }
                }
                $visit = $parent?->visit_id ?? ($data['visit_id'] ?? null);
                $episode = $parent?->episode_id ?? ($data['episode_id'] ?? null);
                $this->link($patient, $visit, 'visit');
                $this->link($patient, $episode, 'pregnancy');
                $record = new ClinicalRecord(['patient_id' => $patient->id, 'kind' => $kind, 'appointment_id' => $appointment?->id, 'visit_id' => $visit, 'episode_id' => $episode, 'parent_id' => $parent?->id, 'request_key' => $data['request_key'], 'request_hash' => $requestHash, 'created_by' => $actor->id, 'status' => 'draft', 'version' => 0]);
            }
            $occurred = app(JalaliDate::class)->parse(\App\Modules\Identity\Domain\MobileNumber::normalize($data['occurred_jalali']));
            [$hours, $minutes] = explode(':', $data['occurred_time']);
            $payload = $data['payload'];
            foreach (ClinicalSchema::fields($kind) as $field => $definition) {
                if ($definition[2] === 'date' && !empty($payload[$field])) {
                    $payload[$field] = app(JalaliDate::class)->parse(\App\Modules\Identity\Domain\MobileNumber::normalize($payload[$field]))->format('Y-m-d');
                }
            }
            $record->forceFill(['payload' => $payload, 'reason' => $parent ? $data['reason'] : $record->reason, 'occurred_at' => $occurred->setTime((int) $hours, (int) $minutes)->utc(), 'updated_by' => $actor->id, 'version' => $record->version + 1])->save();
            app(MedicalAudit::class)->record($actor->id, 'draft.saved', $patient->id, $record->id);
            return $record;
        }, 3);
    }

    public function sign(User $actor, Patient $patient, ClinicalRecord $record, int $version): void
    {
        abort_unless(app(MedicalRecordPolicy::class)->clinical($actor), 403);
        DB::transaction(function () use ($actor, $patient, $record, $version): void {
            Patient::whereKey($patient->id)->lockForUpdate()->firstOrFail();
            $record = ClinicalRecord::where('patient_id', $patient->id)->whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($record->status === 'final' && $record->version === $version + 1) {
                return;
            }
            abort_unless($record->status === 'draft' && $record->version === $version, 409, 'نسخه تغییر کرده است.');
            if ($record->kind === 'visit' && !array_filter($record->payload, fn ($value) => $value !== null && $value !== '')) {
                throw ValidationException::withMessages(['payload' => 'ویزیت خالی قابل نهایی‌سازی نیست.']);
            }
            $record->forceFill(['status' => 'final', 'signed_at' => now(), 'updated_by' => $actor->id, 'version' => $record->version + 1])->save();
            app(MedicalAudit::class)->record($actor->id, $record->parent_id ? 'amendment.signed' : 'record.signed', $patient->id, $record->id);
        }, 3);
    }

    public function link(Patient $patient, ?int $id, string $kind): void
    {
        if ($id) {
            ClinicalRecord::where('patient_id', $patient->id)->where('kind', $kind)->whereKey($id)->firstOrFail();
        }
    }

    public function gestation(ClinicalRecord $episode, \DateTimeInterface $reference): ?array
    {
        $data = $episode->payload;
        $manualAnchor = !empty($data['dating_date']) && ($data['gestation_days'] ?? null) !== null;
        $approvedLmp = ($data['dating_source'] ?? '') === 'lmp' && !empty($data['lmp']);
        if (!$manualAnchor && !$approvedLmp) {
            return null;
        }
        // Only a physician-recorded anchor or an explicitly selected LMP basis is used.
        // EDD is never generated, and ultrasound data never overwrites that basis.
        $start = \Carbon\CarbonImmutable::parse($manualAnchor ? $data['dating_date'] : $data['lmp'], 'Asia/Tehran')->startOfDay();
        $end = \Carbon\CarbonImmutable::instance($reference)->timezone('Asia/Tehran')->startOfDay();
        $days = ($manualAnchor ? (int) $data['gestation_days'] : 0) + (int) $start->diffInDays($end, false);
        return $days < 0 || $days > 315 ? null : ['weeks' => intdiv($days, 7), 'days' => $days % 7, 'reference' => app(JalaliDate::class)->format($end)];
    }
}
