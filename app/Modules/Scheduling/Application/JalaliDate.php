<?php

namespace App\Modules\Scheduling\Application;

use Carbon\CarbonImmutable;
use IntlDateFormatter;
use InvalidArgumentException;

final class JalaliDate
{
    private function formatter(): IntlDateFormatter
    {
        $formatter = new IntlDateFormatter('en_US@calendar=persian', IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'Asia/Tehran', IntlDateFormatter::TRADITIONAL, 'yyyy-MM-dd');
        $formatter->setLenient(false);
        return $formatter;
    }

    public function format(\DateTimeInterface $date): string
    {
        return $this->formatter()->format($date);
    }

    public function parse(string $value): CarbonImmutable
    {
        if (!preg_match('/\A1[34][0-9]{2}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])\z/', $value)) {
            throw new InvalidArgumentException('Invalid Jalali date');
        }
        $time = $this->formatter()->parse($value);
        if ($time === false) {
            throw new InvalidArgumentException('Invalid Jalali date');
        }
        $date = CarbonImmutable::createFromTimestamp($time, 'Asia/Tehran')->startOfDay();
        if ($this->format($date) !== $value) {
            throw new InvalidArgumentException('Invalid Jalali date');
        }
        return $date;
    }

    public function month(string $month): array
    {
        $start = $this->parse($month.'-01');
        $end = $start->addDays(28);
        while (substr($this->format($end->addDay()), 0, 7) === $month) {
            $end = $end->addDay();
        }
        return [$start, $end];
    }
}
