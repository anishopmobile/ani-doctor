<?php

namespace Tests\Unit;

use App\Modules\Identity\Domain\MobileNumber;
use PHPUnit\Framework\TestCase;

final class MobileNumberTest extends TestCase
{
    public function test_persian_and_arabic_digits_are_normalized(): void
    {
        $this->assertSame('09123456789', MobileNumber::normalize(' ۰۹١٢۳۴۵۶۷۸۹ '));
        $this->assertTrue(MobileNumber::isValid('09123456789'));
        $this->assertFalse(MobileNumber::isValid("09123456789\n"));
    }
}
