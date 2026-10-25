<?php

namespace App\Modules\Scheduling\Application;

use App\Modules\Catalog\Infrastructure\Service;
use App\Modules\Scheduling\Domain\BookingReadInterface;
use App\Modules\Scheduling\Domain\SlotCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class AvailabilityService
{
    public function __construct(private readonly ScheduleService $schedule, private readonly BookingReadInterface $bookings, private readonly SlotCalculator $calculator, private readonly JalaliDate $jalali)
    {
    }

    public function days(Service $service, CarbonImmutable $from, CarbonImmutable $to, ?string $excludeReference = null): array
    {
        $today = CarbonImmutable::now('Asia/Tehran')->startOfDay();
        $now = now()->timestamp;
        $max = $today->addDays((int) DB::table('schedule_state')->where('id', 1)->value('horizon_days') - 1);
        $schedules = $this->schedule->range($from, $to);
        $busy = [];
        foreach ($this->bookings->between($from->utc(), $to->addDay()->utc()) as $item) {
            if ($excludeReference !== null && ($item['reference'] ?? null) === $excludeReference) {
                continue;
            }
            if ($item['status'] !== 'confirmed' && !($item['status'] === 'pending_payment' && !empty($item['hold_expires_at']) && CarbonImmutable::parse($item['hold_expires_at'])->timestamp > $now)) {
                continue;
            }
            $busy[] = ['start' => CarbonImmutable::parse($item['start_at'])->timestamp, 'end' => CarbonImmutable::parse($item['end_at'])->timestamp, 'capacity_limit' => $item['capacity_limit'] ?? PHP_INT_MAX];
        }
        $days = [];
        for ($date = $from; $date <= $to; $date = $date->addDay()) {
            $iso = $date->toDateString();
            $jalaliDate = $this->jalali->format($date);
            $reason = $date < $today ? 'past' : ($date > $max ? 'outside_window' : $schedules[$iso]['reason']);
            $slots = [];
            if (!$reason) {
                $shifts = array_map(fn ($shift) => ['start' => $date->addMinutes($shift['start_minute'])->timestamp, 'end' => $date->addMinutes($shift['end_minute'])->timestamp, 'step_minutes' => $shift['step_minutes'], 'capacity' => $shift['capacity']], $schedules[$iso]['shifts']);
                foreach ($this->calculator->calculate($shifts, $service->duration_minutes, $service->buffer_minutes, $service->capacity, $busy) as $slot) {
                    $start = CarbonImmutable::createFromTimestamp($slot['start'], 'UTC');
                    $bookable = $slot['available_capacity'] > 0 && $slot['start'] > $now;
                    $slots[] = ['capacity_limit' => $slot['capacity_limit'], 'start_at_utc' => $start->toIso8601String(), 'end_at_utc' => CarbonImmutable::createFromTimestamp($slot['end'], 'UTC')->toIso8601String(), 'local_date_jalali' => $jalaliDate, 'local_start_time' => $start->setTimezone('Asia/Tehran')->format('H:i'), 'available_capacity' => $slot['start'] > $now ? $slot['available_capacity'] : 0, 'is_bookable' => $bookable, 'unavailable_reason' => $bookable ? null : ($slot['start'] <= $now ? 'past' : 'full')];
                }
                $reason = array_filter($slots, fn ($slot) => $slot['is_bookable']) ? null : ($slots ? 'full' : 'closed');
            }
            $days[] = ['date' => $iso, 'jalali' => $jalaliDate, 'status' => $reason ?? 'available', 'slots' => $slots];
        }
        return $days;
    }
}
