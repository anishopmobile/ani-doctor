<?php

namespace Tests\Unit;

use App\Modules\Scheduling\Application\JalaliDate;
use PHPUnit\Framework\TestCase;

final class JalaliDateTest extends TestCase
{
    public function test_nowruz_roundtrip_and_esfand_lengths(): void
    {
        $dates = new JalaliDate();
        $this->assertSame('2025-03-21', $dates->parse('1404-01-01')->toDateString());
        [$start,$end] = $dates->month('1403-12');
        $this->assertSame(30, (int)$start->diffInDays($end) + 1);
        [$start,$end] = $dates->month('1404-12');
        $this->assertSame(29, (int)$start->diffInDays($end) + 1);
    }
    public function test_invalid_jalali_date_is_not_normalized_to_another_day(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new JalaliDate())->parse('1404-12-30');
    }
}
