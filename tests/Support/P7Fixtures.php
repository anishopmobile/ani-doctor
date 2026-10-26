<?php

namespace Tests\Support;

use App\Modules\Billing\Application\DiscountService;
use App\Modules\Billing\Application\OfflinePaymentService;
use App\Modules\Booking\Application\BookingService;
use App\Modules\Booking\Infrastructure\Appointment;
use Illuminate\Support\Str;

trait P7Fixtures
{
    use BookingFixtures;
    protected function offer(array $changes = []): int
    {
        return app(DiscountService::class)->create($this->admin, ['code' => 'SAVE20', 'kind' => 'percent', 'value' => 20, 'max_discount_irr' => null, 'max_uses' => 10, 'per_patient' => 2, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'service_ids' => [$this->service->id], 'reason' => 'کد ساختگی تست', ...$changes]);
    }
    protected function discounted(array $changes = []): Appointment
    {
        return app(BookingService::class)->create($this->bookingActor($this->patient), [...$this->bookingData($this->service), 'discount_code' => 'SAVE20', ...$changes]);
    }
    protected function collect(Appointment $appointment): Appointment
    {
        return app(OfflinePaymentService::class)->confirmCollectedHold($this->bookingActor($this->secretary), $appointment, $appointment->price_irr, (string) Str::uuid(), 'دریافت ساختگی تست؛ وجه واقعی نیست', 'cash')->fresh();
    }
    protected function cancel(Appointment $appointment): Appointment
    {
        return app(BookingService::class)->change($this->bookingActor($this->patient), $appointment, ['expected_version' => $appointment->version, 'idempotency_key' => (string) Str::uuid(), 'reason' => 'لغو ساختگی'], 'cancel');
    }
    protected function reportFilters(string $kind = 'finance', int $days = 0): array
    {
        $start = now('Asia/Tehran')->addDays($days)->startOfDay();
        return ['kind' => $kind, 'from' => app(\App\Modules\Scheduling\Application\JalaliDate::class)->format($start), 'to' => app(\App\Modules\Scheduling\Application\JalaliDate::class)->format($start), 'start' => $start->clone()->utc()->toDateTimeString(), 'end' => $start->clone()->addDay()->utc()->toDateTimeString(), 'start_date' => $start->toDateString(), 'end_date' => $start->clone()->addDay()->toDateString(), 'sort' => 'date', 'direction' => 'asc'];
    }
}
