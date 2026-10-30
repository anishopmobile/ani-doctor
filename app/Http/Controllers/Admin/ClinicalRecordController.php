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
