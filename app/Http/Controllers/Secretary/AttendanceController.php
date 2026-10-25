<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceRequest;
use App\Modules\Booking\Application\ActorResolver;
use App\Modules\Booking\Application\AppointmentAccess;
use App\Modules\Booking\Application\AttendanceService;

final class AttendanceController extends Controller
{
    public function __invoke(AttendanceRequest $request, string $reference, ActorResolver $actors, AppointmentAccess $access, AttendanceService $attendance)
    {
        $actor = $actors->resolve($request);
        $appointment = $access->query($actor)->where('reference', $reference)->firstOrFail();
        $appointment = $attendance->change($actor, $appointment, $request->validated());
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => ['reference' => $appointment->reference, 'attendance_status' => $appointment->attendance_status, 'booking_status' => $appointment->status, 'version' => $appointment->version], 'error' => null, 'meta' => ['server_now' => now()->toIso8601String()]]);
        }
        return redirect()->route('appointments.show', $appointment->reference)->with('status', 'وضعیت حضور با تاریخچه ثبت شد.');
    }
}
