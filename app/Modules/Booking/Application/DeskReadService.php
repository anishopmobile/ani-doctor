<?php

namespace App\Modules\Booking\Application;

use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Identity\Domain\BookingActor;
use App\Modules\Scheduling\Application\JalaliDate;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

final class DeskReadService
{
    public function __construct(private readonly JalaliDate $jalali)
    {
    }
    public function day(BookingActor $actor, CarbonImmutable $day, array $filters): array
    {
        if (!$actor->userId || !$actor->staff() || !$actor->can('desk.view')) {
            throw new AuthorizationException();
        }
        $base = Appointment::where('local_date', $day->toDateString());
        $now = now();
        $summary = [
            'total' => (clone $base)->count(),
            'scheduled' => (clone $base)->where('status', 'confirmed')->where('attendance_status', 'scheduled')->count(),
            'arrived' => (clone $base)->where('status', 'confirmed')->where('attendance_status', 'arrived')->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
            'no_show' => (clone $base)->where('status', 'no_show')->count(),
            'holds' => (clone $base)->where('status', 'pending_payment')->where('hold_expires_at', '>', $now)->count(),
            'cancelled' => (clone $base)->where('status', 'cancelled')->count(),
            'expired' => (clone $base)->where(fn ($query) => $query->where('status', 'expired')->orWhere(fn ($pending) => $pending->where('status', 'pending_payment')->where('hold_expires_at', '<=', $now)))->count(),
        ];
        if (!empty($filters['query'])) {
            $query = $filters['query'];
            $like = '%'.strtr($query, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $base->where(function ($items) use ($query, $like): void {
                $items->whereHas('patient', fn ($patient) => $patient->whereRaw("name LIKE ? ESCAPE '!'", [$like])->orWhere('contact_mobile', $query)->orWhere('file_number', $query));
                if (preg_match('/\A09[0-9]{9}\z/', $query)) {
                    $items->orWhere('booker_mobile_hash', hash_hmac('sha256', $query, (string) config('app.key')));
                }
            });
        }
        if (!empty($filters['attendance'])) {
            $base->where('attendance_status', $filters['attendance'])->whereIn('status', ['confirmed', 'completed', 'no_show']);
        }
        if (!empty($filters['service_id'])) {
            $base->where('service_id', $filters['service_id']);
        }
        return ['day' => $day, 'jalaliDay' => $this->jalali->format($day), 'previousDay' => $this->jalali->format($day->subDay()), 'nextDay' => $this->jalali->format($day->addDay()), 'summary' => $summary, 'appointments' => $base->with('patient')->orderBy('start_at')->orderBy('id')->paginate(20), 'filters' => $filters];
    }
}
