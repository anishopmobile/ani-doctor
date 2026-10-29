<?php

namespace App\Modules\Scheduling\Application;

use App\Modules\Scheduling\Infrastructure\ScheduleException;
use App\Modules\Scheduling\Infrastructure\WeeklyAnnouncement;
use App\Modules\Scheduling\Infrastructure\WeeklySchedule;
use App\Modules\SettingsAudit\Application\AuditService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Informational read model. Booking must never depend on this service or its table. */
final class WeeklyAnnouncementService
{
    public const DAYS = [6 => 'شنبه', 7 => 'یکشنبه', 1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه'];

    public const STATUSES = ['working' => 'برنامه حضور', 'closed' => 'تعطیل؛ پزشک حضور ندارد', 'official_holiday' => 'تعطیلی رسمی', 'unknown' => 'برنامه‌ای اعلام نشده است'];

    public function __construct(private readonly ScheduleService $schedule, private readonly JalaliDate $jalali, private readonly AuditService $audit)
    {
    }

    public function week(?string $jalali = null): array
    {
        $date = $jalali ? $this->jalali->parse($jalali) : CarbonImmutable::now('Asia/Tehran')->startOfDay();
        $from = $date->subDays(($date->isoWeekday() + 1) % 7);
        return ['days' => $this->range($from, $from->addDays(6)), 'start' => $this->jalali->format($from), 'end' => $this->jalali->format($from->addDays(6)), 'previous' => $this->jalali->format($from->subWeek()), 'next' => $this->jalali->format($from->addWeek())];
    }

    public function range(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $sources = $this->sources($from, $to);
        $overrides = WeeklyAnnouncement::whereBetween('date', [$from->toDateString(), $to->toDateString()])->get()->keyBy('date');
        $days = [];
        for ($date = $from; $date <= $to; $date = $date->addDay()) {
            $iso = $date->toDateString();
            $record = $overrides->get($iso);
            $presence = $record?->presence_override ?? $sources[$iso];
            $days[] = [
                'date' => $iso, 'jalali' => $this->jalali->format($date), 'weekday' => self::DAYS[$date->isoWeekday()],
                'today' => $iso === CarbonImmutable::now('Asia/Tehran')->toDateString(),
                'source' => $sources[$iso], 'presence' => $presence, 'status_label' => self::STATUSES[$presence['status']],
                'manual' => $record?->presence_override !== null,
                'source_changed' => $record?->presence_override !== null && $record->source_snapshot !== $sources[$iso],
                'location' => $record?->location, 'outside_office' => $record?->outside_office ?? false,
                'notes' => $record?->notes, 'version' => $record?->version ?? 0,
            ];
        }
        return $days;
    }

    public function save(CarbonImmutable $date, array $data, int $actor): void
    {
        DB::transaction(function () use ($date, $data, $actor): void {
            $iso = $date->toDateString();
            // Unique date and row lock protect first-save races and stale editor tabs.
            DB::table('weekly_announcements')->insertOrIgnore(['date' => $iso, 'created_at' => now(), 'updated_at' => now()]);
            $record = WeeklyAnnouncement::where('date', $iso)->lockForUpdate()->sole();
            if ($record->version !== (int) $data['expected_version']) {
                throw ValidationException::withMessages(['expected_version' => 'این روز در پنجره دیگری تغییر کرده است؛ صفحه را تازه کنید.']);
            }
            $before = $record->toArray();
            $presence = null;
            if ($data['mode'] === 'manual') {
                $presence = ['status' => $data['status'], 'shifts' => $data['status'] === 'working' ? array_map(fn ($row) => ['start' => $row['start'], 'end' => $row['end'], 'label' => $row['label'] ?? null], $data['shifts']) : []];
            }
            $record->fill(['source_snapshot' => $this->sources($date, $date)[$iso], 'presence_override' => $presence,
                'location' => $data['location'] ?? null, 'outside_office' => $data['outside_office'], 'notes' => $data['notes'] ?? null]);
            $record->version++;
            $record->save();
            $this->audit->record($actor, 'weekly-announcement.saved', $before, $record->toArray());
        }, 3);
    }

    private function sources(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $range = $this->schedule->range($from, $to);
        $weekdays = WeeklySchedule::pluck('weekday')->all();
        $exceptions = ScheduleException::where('start_date', '<=', $to->toDateString())->where('end_date', '>=', $from->toDateString())->get(['start_date', 'end_date']);
        $sources = [];
        for ($day = $from; $day <= $to; $day = $day->addDay()) {
            $iso = $day->toDateString();
            $known = in_array($day->isoWeekday(), $weekdays) || $exceptions->contains(fn ($row) => $row->start_date <= $iso && $row->end_date >= $iso);
            $source = $range[$iso];
            $sources[$iso] = ['status' => !$known ? 'unknown' : ($source['reason'] ? 'closed' : ($source['shifts'] ? 'working' : 'unknown')), 'shifts' => array_map(fn ($row) => ['start' => self::time($row['start_minute']), 'end' => self::time($row['end_minute']), 'label' => null], $source['shifts'])];
        }
        return $sources;
    }

    private static function time(int $minute): string
    {
        return sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    }
}
