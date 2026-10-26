<?php

namespace App\Modules\DynamicForms\Application;

use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Identity\Domain\BookingActor;
use App\Modules\Identity\Infrastructure\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PrivateFormService
{
    public function __construct(private readonly FormSchema $schema)
    {
    }
    public function publish(User $actor, ?int $id, array $data): int
    {
        abort_unless($actor->hasPermission('forms.manage'), 403);
        $fields = $this->schema->definition($data['schema']);
        return DB::transaction(function () use ($actor, $id, $data, $fields): int {
            $form = $id ? DB::table('forms')->where('id', $id)->lockForUpdate()->first() : null;
            abort_if($id && !$form, 404);
            abort_if(($form->published_version ?? 0) !== (int) $data['expected_version'], 409, 'نسخه فرم تغییر کرده است؛ صفحه را تازه کنید.');
            if (!$form) {
                $id = DB::table('forms')->insertGetId(['title' => $data['title'], 'service_id' => $data['service_id'] ?? null, 'page_key' => $data['page_key'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
            }
            $number = ($form->published_version ?? 0) + 1;
            DB::table('form_versions')->insert(['form_id' => $id, 'number' => $number, 'schema' => json_encode($fields), 'created_by' => $actor->id, 'reason' => $data['reason'], 'created_at' => now()]);
            DB::table('forms')->where('id', $id)->update(['title' => $data['title'], 'service_id' => $data['service_id'] ?? null, 'page_key' => $data['page_key'] ?? null, 'is_active' => $data['is_active'], 'published_version' => $number, 'updated_at' => now()]);
            return $id;
        }, 3);
    }
    public function authorize(BookingActor $actor, Appointment $appointment, bool $write = false): void
    {
        // An appointment operator is not automatically a medical-answer reader.
        if ($actor->staff()) {
            abort_unless($actor->can($write ? 'forms.answers.write' : 'forms.answers.view'), 403);
        } else {
            abort_unless($actor->owns($appointment->booker_user_id, $appointment->booker_mobile_hash), 404);
        }
    }
    public function available(Appointment $appointment)
    {
        return DB::table('forms')->where(function ($q) use ($appointment): void {
            $q->where(fn ($active) => $active->where('is_active', true)->where(fn ($assigned) => $assigned->where('service_id', $appointment->service_id)->orWhere('page_key', 'appointment')))
                ->orWhereExists(fn ($history) => $history->selectRaw('1')->from('form_submissions')->join('form_versions', 'form_versions.id', '=', 'form_submissions.form_version_id')->whereColumn('form_versions.form_id', 'forms.id')->where('form_submissions.appointment_id', $appointment->id));
        })->orderBy('id')->get();
    }
    public function assigned(object $form, Appointment $appointment): bool
    {
        return $form->is_active && ($form->service_id === $appointment->service_id || $form->page_key === 'appointment');
    }
    private function context(Appointment $appointment, int $formId): array
    {
        $form = DB::table('forms')->where('id', $formId)->first();
        abort_unless($form, 404);
        $latest = DB::table('form_submissions')->join('form_versions', 'form_versions.id', '=', 'form_submissions.form_version_id')->where('form_submissions.appointment_id', $appointment->id)->where('form_versions.form_id', $formId)->orderByDesc('form_submissions.id')->select('form_submissions.*')->first();
        abort_unless($latest || $this->assigned($form, $appointment), 404);
        $version = $latest ? DB::table('form_versions')->find($latest->form_version_id) : DB::table('form_versions')->where('form_id', $formId)->where('number', $form->published_version)->first();
        abort_unless($version, 404);
        return [$form, $version, $latest];
    }
    public function read(BookingActor $actor, Appointment $appointment, int $formId): array
    {
        $this->authorize($actor, $appointment);
        [$form, $version, $latest] = $this->context($appointment, $formId);
        $this->audit($actor, $appointment, $formId, 'view');
        $history = DB::table('form_submissions')->where('appointment_id', $appointment->id)->where('form_version_id', $version->id)->orderByDesc('id')->limit(20)->get()->map(fn ($s) => (object) ['reference' => $s->reference, 'created_at' => $s->created_at, 'answers' => json_decode(Crypt::decryptString($s->answers), true)]);
        return ['form' => $form, 'version' => $version, 'fields' => json_decode($version->schema, true), 'answers' => $latest ? json_decode(Crypt::decryptString($latest->answers), true) : [], 'latestId' => $latest?->id ?? 0, 'history' => $history];
    }
    public function submit(BookingActor $actor, Appointment $snapshot, int $formId, array $data): string
    {
        $this->authorize($actor, $snapshot, true);
        if (empty($data['consent'])) {
            throw ValidationException::withMessages(['consent' => 'اطلاع از نحوه نگهداری پاسخ و مجوز ثبت لازم است.']);
        }
        $hash = hash_hmac('sha256', json_encode([$snapshot->reference, $formId, $data], JSON_THROW_ON_ERROR), (string) config('app.key'));
        return DB::transaction(function () use ($actor, $snapshot, $formId, $data, $hash): string {
            DB::table('booking_actor_locks')->insertOrIgnore(['key' => $actor->key]);
            DB::table('booking_actor_locks')->where('key', $actor->key)->lockForUpdate()->first();
            $appointment = Appointment::whereKey($snapshot->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $appointment, true);
            $existing = DB::table('form_submissions')->where('actor_hash', $actor->key)->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409);
                return $existing->reference;
            }
            abort_unless(in_array($appointment->effectiveStatus(), ['pending_payment', 'confirmed'], true) && $appointment->attendance_status === 'scheduled', 409, 'ثبت پاسخ در وضعیت فعلی نوبت مجاز نیست.');
            // Share the definition lock with publication so a stale unseen version cannot be submitted.
            DB::table('forms')->where('id', $formId)->sharedLock()->first();
            [$form, $version, $latest] = $this->context($appointment, $formId);
            abort_unless($this->assigned($form, $appointment), 409, 'فرم بایگانی شده و فقط قابل مشاهده است.');
            abort_unless((int) $data['version_id'] === $version->id && (int) $data['expected_submission_id'] === ($latest?->id ?? 0), 409, 'فرم یا پاسخ تغییر کرده است؛ صفحه را تازه کنید.');
            $answers = $this->schema->answers(json_decode($version->schema, true), $data['answers'] ?? []);
            $reference = (string) Str::uuid();
            DB::table('form_submissions')->insert(['reference' => $reference, 'appointment_id' => $appointment->id, 'form_version_id' => $version->id, 'answers' => Crypt::encryptString(json_encode($answers, JSON_THROW_ON_ERROR)), 'actor_id' => $actor->userId, 'actor_hash' => $actor->key, 'idempotency_key' => $data['idempotency_key'], 'payload_hash' => $hash, 'consented_at' => now(), 'created_at' => now()]);
            $this->audit($actor, $appointment, $formId, 'submit');
            return $reference;
        }, 3);
    }
    private function audit(BookingActor $actor, Appointment $appointment, int $formId, string $action): void
    {
        DB::table('form_access_logs')->insert(['appointment_id' => $appointment->id, 'form_id' => $formId, 'actor_id' => $actor->userId, 'actor_hash' => $actor->key, 'action' => $action, 'created_at' => now()]);
    }
}
