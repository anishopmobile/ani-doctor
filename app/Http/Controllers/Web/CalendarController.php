<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalendarRequest;
use App\Modules\Catalog\Infrastructure\Service;
use App\Modules\Scheduling\Application\AvailabilityService;
use App\Modules\Scheduling\Application\JalaliDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

final class CalendarController extends Controller
{
    public function index(JalaliDate $jalali, \Illuminate\Http\Request $request, \App\Modules\Booking\Application\ActorResolver $actors): View
    {
        try {
            $actor = $actors->resolve($request);
        } catch (\App\Modules\Booking\Domain\BookingFailure) {
            $actor = null;
        }
        $services = Service::where('is_active', true);
        if (!$actor?->staff()) {
            $services->where('is_online', true);
        }
        return view('calendar', ['services' => $services->orderBy('name')->get(), 'initialMonth' => substr($jalali->format(CarbonImmutable::now('Asia/Tehran')), 0, 7), 'actor' => $actor,'patients' => $actor ? app(\App\Modules\Patients\Application\BookingPatientService::class)->choices($actor) : collect(), 'editing' => null]);
    }

    public function month(CalendarRequest $request, AvailabilityService $availability, JalaliDate $jalali): JsonResponse
    {
        [$service,$exclude] = $this->selection($request);
        [$from, $to] = $jalali->month($request->validated('month'));
        $days = array_map(function ($day) {
            unset($day['slots']);
            return $day;
        }, $availability->days($service, $from, $to, $exclude));
        return $this->response(['days' => $days]);
    }

    public function day(CalendarRequest $request, AvailabilityService $availability): JsonResponse
    {
        [$service,$exclude] = $this->selection($request);
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $request->validated('date'), 'Asia/Tehran');
        return $this->response($availability->days($service, $date, $date, $exclude)[0]);
    }

    private function selection(CalendarRequest $request): array
    {
        $query = Service::where('is_active', true);
        if (!$request->user()?->hasPermission('appointments.manage')) {
            $query->where('is_online', true);
        }
        $service = $query->findOrFail($request->validated('service_id'));
        $reference = $request->route('reference');
        if ($reference) {
            $actor = app(\App\Modules\Booking\Application\ActorResolver::class)->resolve($request);
            $appointment = app(\App\Modules\Booking\Application\AppointmentAccess::class)->query($actor)->where('reference', $reference)->where('service_id', $service->id)->firstOrFail();
            $service->forceFill(['duration_minutes' => $appointment->duration_minutes,'buffer_minutes' => $appointment->buffer_minutes,'capacity' => $appointment->capacity_limit]);
        }
        return [$service,$reference];
    }

    private function response(array $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'error' => null, 'meta' => ['server_now' => now()->toIso8601String(), 'timezone' => 'Asia/Tehran', 'preview_only' => true]]);
    }
}
