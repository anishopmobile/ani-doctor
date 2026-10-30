<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClinicalRecordRequest;
use App\Modules\MedicalRecords\Application\ClinicalSchema;
use App\Modules\MedicalRecords\Application\ClinicalService;
use App\Modules\MedicalRecords\Application\DocumentService;
use App\Modules\MedicalRecords\Application\MedicalAudit;
use App\Modules\MedicalRecords\Infrastructure\ClinicalRecord;
use App\Modules\MedicalRecords\Infrastructure\MedicalDocument;
use App\Modules\Patients\Infrastructure\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ClinicalRecordController extends Controller
{
    public function specialties(Request $request, Patient $patient)
    {
        $data = $request->validate(['sections' => 'sometimes|array', 'sections.*' => 'required|string|in:menstrual,pregnancy,followup,fertility', 'expected_version' => 'required|integer|min:0']);
        DB::transaction(function () use ($request, $patient, $data): void {
            Patient::whereKey($patient->id)->lockForUpdate()->firstOrFail();
            $profile = \App\Modules\MedicalRecords\Infrastructure\MedicalProfile::where('patient_id', $patient->id)->lockForUpdate()->first();
            abort_unless(($profile?->version ?? 0) === (int) $data['expected_version'], 409, 'اطلاعات تغییر کرده؛ صفحه را تازه کنید.');
            app(\App\Modules\MedicalRecords\Application\ProfileService::class)->ensureFileNumber($patient);
            $profile ??= new \App\Modules\MedicalRecords\Infrastructure\MedicalProfile(['patient_id' => $patient->id, 'created_by' => $request->user()->id, 'version' => 0]);
            $profile->forceFill(['identity' => [...($profile->identity ?? []), 'enabled_sections' => array_values(array_unique($data['sections'] ?? []))], 'status' => $profile->status ?? 'active', 'updated_by' => $request->user()->id, 'version' => $profile->version + 1])->save();
            app(MedicalAudit::class)->record($request->user()->id, 'sections.updated', $patient->id);
        }, 3);
        return redirect()->route('emr.show', $patient);
    }

    public function start(Request $request, Patient $patient, \App\Modules\Booking\Infrastructure\Appointment $appointment, ClinicalService $service)
    {
        abort_unless($appointment->patient_id === $patient->id, 404);
        $record = $service->save($request->user(), $patient, 'visit', ['request_key' => (string) \Illuminate\Support\Str::uuid(), 'appointment_id' => $appointment->id, 'occurred_jalali' => app(\App\Modules\Scheduling\Application\JalaliDate::class)->format(now()), 'occurred_time' => now('Asia/Tehran')->format('H:i'), 'payload' => []]);
        return redirect()->route('emr.records.edit', [$patient, $record])->with('status', 'پیش‌نویس ویزیت نوبت باز شد؛ شرح ویزیت را تکمیل کنید.');
    }

    public function section(Request $request, Patient $patient)
    {
        $data = $request->validate(['section' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(ClinicalSchema::labels()))]]);
        $request->session()->put('emr.section.'.$patient->id, $data['section'] ?? '');
        return redirect()->route('emr.show', $patient);
    }

    public function create(Request $request, Patient $patient, string $kind)
    {
        abort_unless(array_key_exists($kind, ClinicalSchema::labels()), 404);
        $parent = $request->integer('parent') ? ClinicalRecord::where('patient_id', $patient->id)->where('kind', $kind)->where('status', 'final')->findOrFail($request->integer('parent')) : null;
        return $this->editor($request, $patient, $kind, null, $parent);
    }

    public function edit(Request $request, Patient $patient, ClinicalRecord $record)
    {
        abort_unless($record->patient_id === $patient->id, 404);
        return $this->editor($request, $patient, $record->kind, $record, null);
    }

    private function editor(Request $request, Patient $patient, string $kind, ?ClinicalRecord $record, ?ClinicalRecord $parent)
    {
        app(MedicalAudit::class)->record($request->user()->id, 'record.read', $patient->id, $record?->id ?? $parent?->id);
        $records = ClinicalRecord::where('patient_id', $patient->id);
        return view('emr.editor', ['patient' => $patient, 'kind' => $kind, 'record' => $record, 'parent' => $parent, 'successor' => $record ? ClinicalRecord::where('parent_id', $record->id)->first(['id', 'status']) : null, 'fields' => ClinicalSchema::fields($kind), 'visits' => (clone $records)->where('kind', 'visit')->latest('id')->get(['id', 'occurred_at']), 'episodes' => (clone $records)->where('kind', 'pregnancy')->latest('id')->get(['id', 'occurred_at']), 'appointments' => \App\Modules\Booking\Infrastructure\Appointment::where('patient_id', $patient->id)->whereIn('status', ['confirmed', 'completed'])->whereNotNull('arrived_at')->latest('start_at')->limit(100)->get(['id', 'start_at'])]);
    }

    public function store(ClinicalRecordRequest $request, Patient $patient, string $kind, ClinicalService $service)
    {
        $record = $service->save($request->user(), $patient, $kind, $request->validated());
        return redirect()->route('emr.records.edit', [$patient, $record])->with('status', 'پیش‌نویس در پایگاه داده ذخیره شد.');
    }

    public function update(ClinicalRecordRequest $request, Patient $patient, ClinicalRecord $record, ClinicalService $service)
    {
        abort_unless($record->patient_id === $patient->id, 404);
        $service->save($request->user(), $patient, $record->kind, $request->validated(), $record);
        return redirect()->route('emr.records.edit', [$patient, $record])->with('status', 'پیش‌نویس ذخیره شد.');
    }

    public function sign(ClinicalRecordRequest $request, Patient $patient, ClinicalRecord $record, ClinicalService $service)
    {
        $service->sign($request->user(), $patient, $record, $request->integer('expected_version'));
        return redirect()->route('emr.records.edit', [$patient, $record])->with('status', 'نسخه نهایی ثبت شد؛ تغییر بعدی با الحاقیه انجام می‌شود.');
    }

    public function download(Request $request, Patient $patient, MedicalDocument $document, DocumentService $documents)
    {
        $bytes = $documents->bytes($request->user(), $patient, $document);
        $extension = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$document->mime];
        return response()->streamDownload(function () use ($bytes): void {
            foreach (str_split($bytes, 65536) as $chunk) {
                echo $chunk;
            }
        }, 'document-'.$document->id.'.'.$extension, ['Content-Type' => $document->mime, 'Content-Security-Policy' => "sandbox; default-src 'none'", 'Cache-Control' => 'private, no-store']);
    }

    public function review(Request $request, Patient $patient, MedicalDocument $document)
    {
        abort_unless($document->patient_id === $patient->id, 404);
        $data = $request->validate(['status' => 'required|in:reviewed,archived', 'title' => 'nullable|string|max:150']);
        DB::transaction(function () use ($request, $patient, $document, $data): void {
            $document = MedicalDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $document->forceFill(['status' => $data['status'], 'metadata' => [...$document->metadata, 'title' => $data['title'] ?? $document->metadata['title'] ?? null], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()])->save();
            app(MedicalAudit::class)->record($request->user()->id, 'document.'.$data['status'], $patient->id, $document->id);
        });
        return redirect()->route('emr.show', $patient)->with('status', 'وضعیت مدرک ثبت شد؛ فایل قبلی حذف نشد.');
    }

    public function audit(Request $request, Patient $patient)
    {
        app(MedicalAudit::class)->record($request->user()->id, 'audit.read', $patient->id);
        $events = DB::table('configuration_audit_events')->where('action', 'like', 'emr.%')->where('after_state->patient_id', $patient->id)->latest('id')->paginate(30);
        return view('emr.audit', compact('patient', 'events'));
    }

    public function print(Request $request, Patient $patient)
    {
        app(MedicalAudit::class)->record($request->user()->id, 'record.exported', $patient->id);
        $records = ClinicalRecord::where('patient_id', $patient->id)->where('status', 'final')->where('kind', '!=', 'note')->orderBy('occurred_at')->get();
        return view('emr.print', compact('patient', 'records'));
    }
}
