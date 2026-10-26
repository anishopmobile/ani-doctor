<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingRequest;
use App\Modules\Booking\Application\ActorResolver;
use App\Modules\Booking\Application\AppointmentAccess;
use App\Modules\Booking\Application\BookingService;
use App\Modules\Booking\Infrastructure\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Modules\Scheduling\Application\JalaliDate;

final class AppointmentController extends Controller
{
    public function __construct(private readonly ActorResolver $actors, private readonly AppointmentAccess $access)
    {
    }
    public function index(Request $request)
    {
        $actor = $this->actors->resolve($request);
        $appointments = $this->access->query($actor)->with('patient')->orderByDesc('start_at')->paginate(20);
        return view('booking.index', compact('appointments', 'actor'));
    }
    public function show(Request $request, string $reference, JalaliDate $jalali)
    {
        $actor = $this->actors->resolve($request);
        $appointment = $this->access->query($actor)->where('reference', $reference)->with('patient')->firstOrFail();
        Gate::authorize('view', $appointment);
        $wallet = app(\App\Modules\Wallet\Application\WalletService::class)->summary($actor->mobileHash);
        $attempts = \App\Modules\Payments\Infrastructure\PaymentAttempt::where('appointment_id', $appointment->id)->orderByDesc('id')->limit(20)->get();
        $emergency = \Illuminate\Support\Facades\DB::table('payment_settings')->where('id', 1)->value('emergency_until');
        $emergencyActive = $emergency && now()->lessThan($emergency);
        $attendanceActions = $actor->can('attendance.manage') ? app(\App\Modules\Booking\Application\AttendanceService::class)->actions($appointment) : [];
        $attendanceEvents = $actor->can('attendance.manage') ? \Illuminate\Support\Facades\DB::table('attendance_events')->where('appointment_id', $appointment->id)->orderByDesc('id')->limit(50)->get() : collect();
        return view('booking.show', compact('appointment', 'actor', 'jalali', 'wallet', 'attempts', 'emergencyActive', 'attendanceActions', 'attendanceEvents'));
    }
    public function store(BookingRequest $request, BookingService $bookings)
    {
        $appointment = $bookings->create($this->actors->resolve($request), $request->validated());
        return $this->respond($request, $appointment, 201);
    }
    public function edit(Request $request, string $reference, JalaliDate $jalali)
    {
        $actor = $this->actors->resolve($request);
        $editing = $this->access->query($actor)->where('reference', $reference)->firstOrFail();
        Gate::authorize('view', $editing);
        $service = \App\Modules\Catalog\Infrastructure\Service::withTrashed()->findOrFail($editing->service_id);
        $service->forceFill(['name' => $editing->service_title,'price_irr' => $editing->price_irr,'duration_minutes' => $editing->duration_minutes,'buffer_minutes' => $editing->buffer_minutes,'capacity' => $editing->capacity_limit]);
        return view('calendar', ['actor' => $actor,'editing' => $editing,'services' => collect([$service]),'patients' => collect(),'initialMonth' => substr($jalali->format($editing->start_at), 0, 7)]);
    }
    public function change(BookingRequest $request, string $reference, BookingService $bookings)
    {
        $actor = $this->actors->resolve($request);
        $appointment = $this->access->query($actor)->where('reference', $reference)->firstOrFail();
        Gate::authorize('view', $appointment);
        $appointment = $bookings->change($actor, $appointment, $request->validated(), $request->route('booking_action'));
        return $this->respond($request, $appointment);
    }
    private function respond(Request $request, Appointment $appointment, int $status = 200)
    {
        if ($request->expectsJson() || $request->is('api/v1/*')) {
            return response()->json([
                'success' => true,'data' => ['reference' => $appointment->reference,'booking_status' => $appointment->effectiveStatus(),
                    'payment_status' => $appointment->payment_status,'hold_expires_at' => $appointment->hold_expires_at?->toIso8601String(),
                    'version' => $appointment->version,'amount_irr' => $appointment->price_irr],'error' => null,'meta' => ['server_now' => now()->toIso8601String()],
            ], $status);
        }
        return redirect()->route('appointments.show', $appointment->reference)->with('status', 'درخواست ثبت شد؛ وضعیت نوبت را در این صفحه بررسی کنید.');
    }
}
