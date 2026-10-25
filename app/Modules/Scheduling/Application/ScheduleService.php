<?php

namespace App\Modules\Scheduling\Application;

use App\Modules\Scheduling\Domain\BookingReadInterface;
use App\Modules\Scheduling\Infrastructure\ScheduleException;
use App\Modules\Scheduling\Infrastructure\WeeklySchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ScheduleService
{
    public function __construct(private readonly BookingReadInterface $bookings, private readonly \App\Modules\SettingsAudit\Application\AuditService $auditService)
    {
    }

    public function range(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $weekly = WeeklySchedule::with('shifts')->get()->keyBy('weekday');
        $exceptions = ScheduleException::where('start_date', '<=', $to->toDateString())->where('end_date', '>=', $from->toDateString())->get();
        $result = [];
        for ($day = $from; $day <= $to; $day = $day->addDay()) {
            $date = $day->toDateString();
            $exception = $exceptions->first(fn ($item) => $item->start_date <= $date && $item->end_date >= $date);
            $normal = $weekly->get($day->isoWeekday());
            $closed = $exception ? $exception->kind === 'closed' : (!$normal || $normal->is_closed);
            $result[$date] = ['reason' => $closed ? 'holiday' : null, 'shifts' => $closed ? [] : ($exception ? $exception->shifts : $normal->shifts->toArray())];
        }
        return $result;
    }

    public function saveWeekly(array $data, int $actor): void
    {
        DB::transaction(function () use ($data, $actor): void {
            $this->lock();
            $today = CarbonImmutable::now('Asia/Tehran')->startOfDay();
            $affected = count(array_filter($this->bookings->between($today->utc(), $today->addDays(366)->utc()), fn ($item) => $item['status'] === 'confirmed' && CarbonImmutable::parse($item['start_at'])->setTimezone('Asia/Tehran')->isoWeekday() === (int) $data['weekday']));
            $this->acknowledge($affected, !empty($data['confirm_affected']));
            $day = WeeklySchedule::firstOrNew(['weekday' => $data['weekday']]);
            $before = $day->exists ? $day->load('shifts')->toArray() : null;
            $day->is_closed = $data['is_closed'];
            $day->save();
            $day->shifts()->delete();
            $day->shifts()->createMany($data['is_closed'] ? [] : $data['shifts']);
            $this->audit($actor, 'weekly.saved', $before, $day->fresh()->load('shifts')->toArray());
        }, 3);
    }

    public function saveException(array $data, int $actor): void
    {
        DB::transaction(function () use ($data, $actor): void {
            $this->lock();
            if (ScheduleException::where('start_date', '<=', $data['end_date'])->where('end_date', '>=', $data['start_date'])->exists()) {
                throw ValidationException::withMessages(['start_jalali' => 'این بازه با استثنای موجود تداخل دارد؛ ابتدا استثنای قبلی را حذف کنید.']);
            }
            $from = CarbonImmutable::parse($data['start_date'], 'Asia/Tehran');
            $to = CarbonImmutable::parse($data['end_date'], 'Asia/Tehran')->addDay();
            $affected = $this->affectedCount($from, $to);
            if ($affected > 0 && empty($data['confirm_affected'])) {
                throw ValidationException::withMessages(['confirm_affected' => "این تغییر ممکن است بر {$affected} نوبت قطعی اثر بگذارد. تأیید آگاهانه لازم است؛ نوبت‌ها حذف نمی‌شوند."]);
            }
            $exception = ScheduleException::create(array_intersect_key($data, array_flip(['start_date', 'end_date', 'kind', 'shifts', 'reason'])));
            $this->audit($actor, 'exception.created', null, [...$exception->toArray(), 'affected_confirmed' => $affected]);
        }, 3);
    }

    public function removeException(ScheduleException $exception, int $actor, bool $confirmed = false): void
    {
        DB::transaction(function () use ($exception, $actor, $confirmed): void {
            $this->lock();
            $exception = ScheduleException::whereKey($exception->id)->lockForUpdate()->firstOrFail();
            $from = CarbonImmutable::parse($exception->start_date, 'Asia/Tehran');
            $to = CarbonImmutable::parse($exception->end_date, 'Asia/Tehran')->addDay();
            $this->acknowledge($this->affectedCount($from, $to), $confirmed);
            $this->audit($actor, 'exception.removed', $exception->toArray(), null);
            $exception->delete();
        });
    }

    public function setHorizon(int $days, int $actor): void
    {
        DB::transaction(function () use ($days, $actor): void {
            $this->lock();
            $old = DB::table('schedule_state')->where('id', 1)->value('horizon_days');
            DB::table('schedule_state')->where('id', 1)->update(['horizon_days' => $days]);
            $this->audit($actor, 'horizon.changed', ['horizon_days' => $old], ['horizon_days' => $days]);
        });
    }

    private function lock(): void
    {
        DB::table('schedule_state')->where('id', 1)->lockForUpdate()->first();
        DB::table('schedule_state')->where('id', 1)->increment('version');
    }

    private function affectedCount(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return count(array_filter($this->bookings->between($from->utc(), $to->utc()), fn ($item) => $item['status'] === 'confirmed'));
    }

    private function acknowledge(int $count, bool $confirmed): void
    {
        if ($count > 0 && !$confirmed) {
            throw ValidationException::withMessages(['confirm_affected' => "این تغییر بر {$count} نوبت قطعی اثر دارد. پیامد را تأیید کنید؛ هیچ نوبتی حذف نمی‌شود."]);
        }
    }

    private function audit(int $actor, string $action, ?array $before, ?array $after): void
    {
        $this->auditService->record($actor, $action, $before, $after);
    }
}
