<?php

namespace Tests\Unit;

use App\Modules\Scheduling\Domain\SlotCalculator;
use PHPUnit\Framework\TestCase;

final class SlotCalculatorTest extends TestCase
{
    public function test_invalid_step_cannot_cause_an_infinite_loop(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SlotCalculator())->calculate([['start' => 0,'end' => 100,'step_minutes' => 0,'capacity' => 1]], 30, 0, 1, []);
    }

    private function shifts(int $capacity = 1): array
    {
        return [['start' => 0,'end' => 18000,'step_minutes' => 30,'capacity' => $capacity]];
    }
    public function test_five_hour_shift_provides_ten_starts_and_long_service_blocks_next_start(): void
    {
        $calculator = new SlotCalculator();
        $slots = $calculator->calculate($this->shifts(), 30, 0, 1, []);
        $this->assertCount(10, $slots);
        $this->assertSame(16200, $slots[9]['start']);
        $slots = $calculator->calculate($this->shifts(), 30, 0, 1, [['start' => 0,'end' => 3600]]);
        $this->assertSame(0, $slots[0]['available_capacity']);
        $this->assertSame(0, $slots[1]['available_capacity']);
        $this->assertSame(1, $slots[2]['available_capacity']);
    }
    public function test_sweep_does_not_sum_non_simultaneous_overlaps(): void
    {
        $slots = (new SlotCalculator())->calculate($this->shifts(2), 60, 0, 2, [['start' => 0,'end' => 1800],['start' => 1800,'end' => 3600]]);
        $this->assertSame(1, $slots[0]['available_capacity']);
    }
    public function test_buffer_shift_end_and_existing_capacity_limit_are_respected(): void
    {
        $slots = (new SlotCalculator())->calculate($this->shifts(3), 60, 30, 3, [['start' => 1800,'end' => 2700,'capacity_limit' => 1]]);
        $this->assertCount(8, $slots);
        $this->assertSame(0, $slots[0]['available_capacity']);
        $this->assertSame(3, $slots[2]['available_capacity']);
        $this->assertSame(18000, $slots[7]['occupied_until']);
    }
}
