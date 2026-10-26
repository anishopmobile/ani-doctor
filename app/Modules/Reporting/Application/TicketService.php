<?php

namespace App\Modules\Reporting\Application;

use App\Modules\Booking\Application\BookingService;
use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Identity\Domain\BookingActor;
use App\Modules\Scheduling\Application\JalaliDate;

final class TicketService
{
    public function build(BookingActor $actor, Appointment $appointment, string $format): array
    {
        app(BookingService::class)->authorize($actor, $appointment);
        app(ReportService::class)->audit($actor->userId, $actor->key, 'ticket', $format, ['reference' => $appointment->reference], 1);
        return ['title' => 'برگه نوبت مطب', 'kind' => 'ticket', 'from' => '', 'to' => '', 'generated_at' => now()->toIso8601String(), 'generated_label' => app(JalaliDate::class)->format(now()).' '.now('Asia/Tehran')->format('H:i').' به وقت تهران', 'basis' => 'این برگه فقط وضعیت فعلی نوبت را نشان می‌دهد؛ اطلاعات فرم خصوصی و یادداشت داخلی در آن نیست. نام پزشک و نشانی فقط از تنظیمات عمومی مطب چاپ می‌شوند.', 'summary' => [...array_filter(['پزشک' => config('reporting.doctor_name'), 'نشانی مطب' => config('reporting.address')]), 'نام بیمار' => $appointment->patient->name, 'کد پیگیری' => $appointment->reference, 'تاریخ شمسی' => app(JalaliDate::class)->format($appointment->start_at), 'ساعت تهران' => $appointment->start_at->setTimezone('Asia/Tehran')->format('H:i'), 'وضعیت نوبت' => ReportService::STATUS[$appointment->effectiveStatus()], 'وضعیت پرداخت' => ['paid' => 'تسویه‌شده', 'unpaid' => 'پرداخت‌نشده', 'credited' => 'برگشته به اعتبار'][$appointment->payment_status] ?? 'نیازمند بررسی'], 'sections' => []];
    }
}
