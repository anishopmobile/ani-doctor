<?php

namespace Tests\Support;

use App\Modules\Identity\Domain\BookingActor;
use App\Modules\Catalog\Infrastructure\Service;
use App\Modules\Identity\Infrastructure\User;
use App\Modules\Identity\Infrastructure\Role;
use App\Modules\Scheduling\Application\ScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

trait BookingFixtures
{
    protected function booker(string $mobile = '09123456789', string $role = 'patient'): User
    {
        $user = User::firstOrCreate(['mobile' => $mobile]);
        $user->forceFill(['mobile_verified_at' => now()])->save();
        $user->roles()->sync([Role::where('name', $role)->sole()->id]);
        return $user;
    }
    protected function bookingActor(User $user): BookingActor
    {
        return new BookingActor($user->id, $user->mobile, array_values(array_filter(['appointments.manage','appointments.override_capacity','appointments.override_window','booking.settings', 'desk.view', 'desk.book', 'appointments.urgent', 'attendance.manage', 'patients.manage', 'payments.collect', 'payments.settings', 'refunds.manage', 'payments.review'], fn ($p) => $user->hasPermission($p))), (string)config('app.key'));
    }
    protected function bookingServiceFixture(User $operator): Service
    {
        $service = Service::create(['name' => 'خدمت آزمایشی','price_irr' => 1000000,'duration_minutes' => 30,'buffer_minutes' => 0,'capacity' => 1,'is_active' => true,'is_online' => true]);
        foreach (range(1, 7) as $weekday) {
            app(ScheduleService::class)->saveWeekly(['weekday' => $weekday,'is_closed' => false,'shifts' => [['start_minute' => 8 * 60,'end_minute' => 22 * 60,'step_minutes' => 30,'capacity' => 1]]], $operator->id);
        }
        return $service;
    }
    protected function bookingData(Service $service, ?string $start = null): array
    {
        return ['service_id' => $service->id,'expected_price_irr' => $service->price_irr,'start_at' => $start ?? CarbonImmutable::now('Asia/Tehran')->addDays(3)->setTime(17, 0)->utc()->toIso8601String(),'patient_name' => 'بیمار آزمایشی','idempotency_key' => (string)Str::uuid()];
    }
}
