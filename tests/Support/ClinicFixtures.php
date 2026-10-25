<?php

namespace Tests\Support;

use App\Modules\Booking\Application\DeskBookingService;
use App\Modules\Booking\Infrastructure\Appointment;
use Illuminate\Support\Str;

trait ClinicFixtures
{
    use BookingFixtures;
    private int $clinicSequence = 0;
    protected function deskData(array $changes = []): array
    {
        return [...$this->bookingData($this->service), 'booking_kind' => 'phone', 'booker_mobile' => sprintf('0912%07d', ++$this->clinicSequence), 'reason' => 'ثبت تلفنی آزمایشی', 'settlement' => 'hold', ...$changes];
    }
    protected function cashData(array $changes = []): array
    {
        return $this->deskData(['settlement' => 'clinic_paid', 'received_irr' => $this->service->price_irr, 'receipt' => (string) Str::uuid(), 'method' => 'cash', ...$changes]);
    }
    protected function clinicAppointment(array $changes = []): Appointment
    {
        return app(DeskBookingService::class)->create($this->bookingActor($this->secretary), $this->cashData($changes))->fresh();
    }
    protected function attendanceData(Appointment $appointment, string $action, array $changes = []): array
    {
        return ['idempotency_key' => (string) Str::uuid(), 'expected_version' => $appointment->version, 'action' => $action, 'reason' => 'رویداد حضور آزمایشی', ...$changes];
    }
}
