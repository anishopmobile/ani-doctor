<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Http\Requests\MedicalDocumentRequest;
use App\Http\Requests\MedicalProfileRequest;
use App\Modules\MedicalRecords\Application\DocumentService;
use App\Modules\MedicalRecords\Application\MedicalAudit;
use App\Modules\MedicalRecords\Application\ProfileService;
use App\Modules\MedicalRecords\Infrastructure\ClinicalRecord;
use App\Modules\MedicalRecords\Infrastructure\MedicalDocument;
use App\Modules\MedicalRecords\Infrastructure\MedicalProfile;
use App\Modules\Patients\Infrastructure\Patient;
use App\Policies\MedicalRecordPolicy;
use Illuminate\Http\Request;

final class MedicalRecordController extends Controller
{
    public function index(Request $request, ProfileService $profiles)
    {
        $filter = $request->session()->get('emr.filter', []);
        app(MedicalAudit::class)->record($request->user()->id, 'directory.read');
        return view('emr.index', ['patients' => $profiles->search($filter)->paginate(20), 'filter' => $filter]);
    }

    public function search(MedicalProfileRequest $request)
    {
        $request->session()->put('emr.filter', $request->validated());
        app(MedicalAudit::class)->record($request->user()->id, 'directory.searched');
        return redirect()->route('emr.index');
    }

    public function create()
    {
        return view('emr.profile', ['patient' => null, 'profile' => null]);
    }

    public function edit(Request $request, Patient $patient)
    {
        app(MedicalAudit::class)->record($request->user()->id, 'profile.read', $patient->id);
        return view('emr.profile', ['patient' => $patient, 'profile' => MedicalProfile::where('patient_id', $patient->id)->first()]);
    }

    public function save(MedicalProfileRequest $request, ProfileService $profiles, ?Patient $patient = null)
    {
        $patient = $profiles->save($request->user(), $request->validated(), $patient);
        return redirect()->route('emr.show', $patient)->with('status', 'اطلاعات اداری پرونده ذخیره شد.');
    }

    public function show(Request $request, Patient $patient)
    {
        $clinical = app(MedicalRecordPolicy::class)->clinical($request->user());
        app(MedicalAudit::class)->record($request->user()->id, $clinical ? 'clinical.read' : 'profile.read', $patient->id);
        $data = ['patient' => $patient, 'profile' => MedicalProfile::where('patient_id', $patient->id)->first(), 'clinical' => $clinical];
        if ($clinical) {
            $data['visibleLabels'] = app(\App\Modules\MedicalRecords\Application\SpecialtySections::class)->visible($patient, $data['profile']);
            $data['section'] = $request->session()->get('emr.section.'.$patient->id, '');
            $data['records'] = ClinicalRecord::where('patient_id', $patient->id)->when($data['section'] !== '', fn ($query) => $query->where('kind', $data['section']))->orderByDesc('occurred_at')->orderByDesc('id')->paginate(20, ['*'], 'records_page');
            $data['documents'] = MedicalDocument::where('patient_id', $patient->id)->orderByDesc('id')->paginate(10, ['*'], 'documents_page');
            $data['alerts'] = ClinicalRecord::where('patient_id', $patient->id)->where('kind', 'allergy')->where('status', 'final')->whereNotIn('id', ClinicalRecord::whereNotNull('parent_id')->where('status', 'final')->select('parent_id'))->latest('id')->limit(20)->get();
            $data['lastVisit'] = ClinicalRecord::where('patient_id', $patient->id)->where('kind', 'visit')->where('status', 'final')->latest('occurred_at')->first();
            $data['episodes'] = ClinicalRecord::where('patient_id', $patient->id)->where('kind', 'pregnancy')->where('status', 'final')->whereNotIn('id', ClinicalRecord::whereNotNull('parent_id')->where('status', 'final')->select('parent_id'))->latest('id')->limit(20)->get();
        } else {
            // Explicit projection: neither titles nor clinical counters are loaded for reception.
            $data['uploads'] = MedicalDocument::where('patient_id', $patient->id)->where('created_by', $request->user()->id)->latest('id')->paginate(10, ['id', 'status', 'created_at']);
        }
        return view('emr.show', $data);
    }

    public function upload(MedicalDocumentRequest $request, Patient $patient, DocumentService $documents)
    {
        $documents->upload($request->user(), $patient, $request->file('file'), $request->validated());
        return redirect()->route('emr.show', $patient)->with('status', 'مدرک خصوصی برای بررسی پزشک دریافت شد.');
    }
}
