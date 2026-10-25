<?php

namespace App\Modules\Scheduling\Domain;

final class SlotCalculator
{
    /** All intervals are half-open [start,end) Unix timestamps, including buffers. */
    public function calculate(array $shifts, int $duration, int $buffer, int $serviceCapacity, array $busy): array
    {
        if ($duration <= 0 || $buffer < 0 || $serviceCapacity < 1) {
            throw new \InvalidArgumentException('Invalid service timing or capacity');
        }
        $slots = [];
        $length = ($duration + $buffer) * 60;
        foreach ($shifts as $shift) {
            if ($shift['step_minutes'] <= 0 || $shift['end'] <= $shift['start'] || $shift['capacity'] < 1) {
                throw new \InvalidArgumentException('Invalid shift');
            }
            for ($start = $shift['start']; $start + $length <= $shift['end']; $start += $shift['step_minutes'] * 60) {
                $end = $start + $length;
                $overlaps = array_filter($busy, fn ($item) => $item['start'] < $end && $item['end'] > $start);
                $points = [$start];
                foreach ($overlaps as $item) {
                    if ($item['start'] > $start) {
                        $points[] = $item['start'];
                    }
                    if ($item['end'] < $end) {
                        $points[] = $item['end'];
                    }
                }
                $remaining = min($serviceCapacity, $shift['capacity']);
                foreach (array_unique($points) as $point) {
                    $active = array_filter($overlaps, fn ($item) => $item['start'] <= $point && $item['end'] > $point);
                    $limit = min($serviceCapacity, $shift['capacity']);
                    foreach ($active as $item) {
                        $limit = min($limit, $item['capacity_limit'] ?? $limit);
                    }
                    $remaining = min($remaining, max(0, $limit - count($active)));
                }
                $slots[] = ['start' => $start, 'end' => $start + $duration * 60, 'occupied_until' => $end, 'available_capacity' => $remaining];
            }
        }
        return $slots;
    }
}
