<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeskBookingRequest;
use App\Http\Requests\DeskDayRequest;
use App\Modules\Booking\Application\ActorResolver;
use App\Modules\Booking\Application\DeskBookingService;
use App\Modules\Booking\Application\DeskReadService;
use App\Modules\Catalog\Infrastructure\Service;
use App\Modules\Scheduling\Application\JalaliDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

final class DeskController extends Controller
{
    public function index(DeskDayRequest $request, ActorResolver $actors, DeskReadService $desk, JalaliDate $jalali)
    {
        $day = $request->validated('date') ? $jalali->parse($request->validated('date')) : CarbonImmutable::now('Asia/Tehran')->startOfDay();
        return view('desk.index', [...$desk->day($actors->resolve($request), $day, $request->session()->get('clinic.desk_filters', [])), 'services' => Service::orderBy('name')->get(), 'jalali' => $jalali]);
    }
    public function filter(DeskDayRequest $request)
    {
        $data = $request->validated();
        $request->session()->put('clinic.desk_filters', $request->boolean('clear') ? [] : array_intersect_key($data, array_flip(['query', 'attendance', 'service_id'])));
        return redirect()->route('desk', array_filter(['date' => $data['date'] ?? null]));
    }
    public function create(Request $request, ActorResolver $actors, JalaliDate $jalali)
    {
        $today = CarbonImmutable::now('Asia/Tehran')->startOfDay();
        $request->validate(['patient_id' => 'nullable|integer|min:1|exists:patients,id']);
        $selected = old('patient_id', $request->input('patient_id'));
        $patients = $selected ? \App\Modules\Patients\Infrastructure\Patient::whereKey($selected)->get(['id', 'name', 'file_number', 'contact_mobile']) : collect();
        return view('calendar', ['services' => Service::where('is_active', true)->orderBy('name')->get(), 'initialMonth' => substr($jalali->format($today), 0, 7), 'actor' => $actors->resolve($request), 'patients' => $patients, 'editing' => null, 'deskBooking' => true, 'today' => $today, 'jalaliToday' => $jalali->format($today)]);
    }
    public function store(DeskBookingRequest $request, ActorResolver $actors, DeskBookingService $desk)
    {
        $appointment = $desk->create($actors->resolve($request), $request->validated());
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => ['reference' => $appointment->reference, 'booking_status' => $appointment->effectiveStatus(), 'payment_status' => $appointment->payment_status, 'balance_due_irr' => $appointment->balance_due_irr, 'version' => $appointment->version], 'error' => null, 'meta' => ['server_now' => now()->toIso8601String()]], 201);
        }
        return redirect()->route('appointments.show', $appointment->reference)->with('status', 'نوبت ثبت شد؛ وضعیت قطعی و مالی را در این صفحه بررسی کنید.');
    }
}
