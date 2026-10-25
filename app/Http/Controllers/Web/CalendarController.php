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
    public function index(JalaliDate $jalali): View
    {
        return view('calendar', ['services' => Service::where('is_active', true)->where('is_online', true)->orderBy('name')->get(), 'initialMonth' => substr($jalali->format(CarbonImmutable::now('Asia/Tehran')), 0, 7)]);
    }

    public function month(CalendarRequest $request, AvailabilityService $availability, JalaliDate $jalali): JsonResponse
    {
        $service = Service::where('is_active', true)->where('is_online', true)->findOrFail($request->validated('service_id'));
        [$from, $to] = $jalali->month($request->validated('month'));
        $days = array_map(function ($day) {
            unset($day['slots']);
            return $day;
        }, $availability->days($service, $from, $to));
        return $this->response(['days' => $days]);
    }

    public function day(CalendarRequest $request, AvailabilityService $availability): JsonResponse
    {
        $service = Service::where('is_active', true)->where('is_online', true)->findOrFail($request->validated('service_id'));
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $request->validated('date'), 'Asia/Tehran');
        return $this->response($availability->days($service, $date, $date)[0]);
    }

    private function response(array $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'error' => null, 'meta' => ['server_now' => now()->toIso8601String(), 'timezone' => 'Asia/Tehran', 'preview_only' => true]]);
    }
}
