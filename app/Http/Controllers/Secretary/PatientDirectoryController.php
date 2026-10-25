<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Http\Requests\PatientDirectoryRequest;
use App\Modules\Booking\Application\ActorResolver;
use App\Modules\Booking\Application\AppointmentAccess;
use App\Modules\Patients\Application\PatientDirectoryService;
use App\Modules\Patients\Infrastructure\Patient;
use App\Modules\Scheduling\Application\JalaliDate;
use Illuminate\Http\Request;

final class PatientDirectoryController extends Controller
{
    public function index(Request $request, ActorResolver $actors, PatientDirectoryService $patients)
    {
        $query = $request->session()->get('clinic.patient_search', '');
        return view('desk.patients', ['patients' => $patients->search($actors->resolve($request), $query)->paginate(20), 'query' => $query]);
    }
    public function search(PatientDirectoryRequest $request, ActorResolver $actors, PatientDirectoryService $patients)
    {
        $query = $request->validated('query') ?? '';
        if ($request->expectsJson()) {
            $items = $patients->search($actors->resolve($request), $query)->limit(20)->get(['id', 'name', 'contact_mobile', 'file_number']);
            return response()->json(['success' => true, 'data' => ['patients' => $items], 'error' => null, 'meta' => (object) []]);
        }
        $request->session()->put('clinic.patient_search', $request->boolean('clear') ? '' : $query);
        return redirect()->route('patients.index');
    }
    public function save(PatientDirectoryRequest $request, ActorResolver $actors, PatientDirectoryService $patients, ?Patient $patient = null)
    {
        $patient = $patients->save($actors->resolve($request), $request->validated(), $patient);
        return redirect()->route('patients.show', $patient)->with('status', 'مشخصات پایه بیمار ثبت شد؛ حساب یا بیمار دیگری ادغام نشد.');
    }
    public function show(Request $request, Patient $patient, ActorResolver $actors, AppointmentAccess $access, JalaliDate $jalali)
    {
        return view('desk.patient', ['patient' => $patient, 'appointments' => $access->query($actors->resolve($request))->where('patient_id', $patient->id)->orderByDesc('start_at')->paginate(20), 'jalali' => $jalali]);
    }
}
