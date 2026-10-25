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
        $busy = $this->busy($from, $to, $excludeReference);
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

    /** A trusted staff use case may request an off-grid start, still entirely inside an open shift. */
    public function candidate(Service $service, CarbonImmutable $start, ?string $excludeReference = null, bool $offGrid = false): ?array
    {
        $date = $start->setTimezone('Asia/Tehran')->startOfDay();
        if (!$offGrid) {
            foreach ($this->days($service, $date, $date, $excludeReference)[0]['slots'] as $slot) {
                if (CarbonImmutable::parse($slot['start_at_utc'])->timestamp === $start->timestamp) {
                    return $slot;
                }
            }
            return null;
        }
        $today = CarbonImmutable::now('Asia/Tehran')->startOfDay();
        $max = $today->addDays((int) DB::table('schedule_state')->where('id', 1)->value('horizon_days') - 1);
        $schedule = $this->schedule->range($date, $date)[$date->toDateString()];
        if ($date < $today || $date > $max || $schedule['reason'] || !$start->isFuture() || $start->second !== 0) {
            return null;
        }
        foreach ($schedule['shifts'] as $shift) {
            $from = $date->addMinutes($shift['start_minute']);
            $to = $date->addMinutes($shift['end_minute']);
            if ($start < $from || $start->addMinutes($service->duration_minutes + $service->buffer_minutes) > $to) {
                continue;
            }
            $candidateShift = ['start' => $start->timestamp, 'end' => $to->timestamp, 'step_minutes' => (int) ceil(($to->timestamp - $start->timestamp) / 60) + 1, 'capacity' => $shift['capacity']];
            $slot = $this->calculator->calculate([$candidateShift], $service->duration_minutes, $service->buffer_minutes, $service->capacity, $this->busy($date, $date, $excludeReference))[0];
            return ['capacity_limit' => $slot['capacity_limit'], 'is_bookable' => $slot['available_capacity'] > 0];
        }
        return null;
    }

    private function busy(CarbonImmutable $from, CarbonImmutable $to, ?string $excludeReference): array
    {
        $busy = [];
        $now = now()->timestamp;
        foreach ($this->bookings->between($from->utc(), $to->addDay()->utc()) as $item) {
            if ($excludeReference !== null && ($item['reference'] ?? null) === $excludeReference) {
                continue;
            }
            if (!in_array($item['status'], ['confirmed', 'completed', 'no_show'], true) && !($item['status'] === 'pending_payment' && !empty($item['hold_expires_at']) && CarbonImmutable::parse($item['hold_expires_at'])->timestamp > $now)) {
                continue;
            }
            $busy[] = ['start' => CarbonImmutable::parse($item['start_at'])->timestamp, 'end' => CarbonImmutable::parse($item['end_at'])->timestamp, 'capacity_limit' => $item['capacity_limit'] ?? PHP_INT_MAX];
        }
        return $busy;
    }
}
