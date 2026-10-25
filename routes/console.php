<?php

use App\Modules\Identity\Application\StaffService;
use App\Modules\Identity\Domain\MobileNumber;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('identity:provision-staff {mobile} {role}', function (StaffService $service): int {
    try {
        $service->provision(MobileNumber::normalize($this->argument('mobile')), $this->argument('role'));
    } catch (\InvalidArgumentException) {
        $this->error('شماره موبایل یا نقش معتبر نیست؛ نقش باید admin یا secretary باشد.');
        return 1;
    }
    $this->info('نقش ثبت شد؛ ورود همچنان نیازمند OTP است.');
    return 0;
})->purpose('Assign a staff role from the trusted server console; never seeds default credentials');

Artisan::command('identity:prune-otp', function (): void {
    // Keep stable lock rows and cooldown timestamps; erase expired secrets only.
    DB::table('otp_challenges')->where('expires_at', '<=', now())->update(['code_hash' => null, 'status' => 'expired']);
});
Schedule::command('identity:prune-otp')->hourly()->withoutOverlapping();
Artisan::command('booking:expire-holds', function (\App\Modules\Booking\Application\BookingService $bookings): void {
    \App\Modules\Booking\Infrastructure\Appointment::where('status', 'pending_payment')->where('hold_expires_at', '<=', now())->select('id')->chunkById(100, function ($items) use ($bookings): void {
        foreach ($items as $item) {
            $bookings->expire($item->id);
        }
    });
});
Schedule::command('booking:expire-holds')->everyMinute()->withoutOverlapping();
Artisan::command('payments:reconcile', function (\App\Modules\Payments\Application\PaymentService $payments): void {
    $attempts = \App\Modules\Payments\Infrastructure\PaymentAttempt::whereIn('state', ['redirected', 'uncertain', 'failed'])->whereNotNull('authority')->where('updated_at', '<=', now()->subMinutes(2))->orderBy('updated_at')->limit(100)->get();
    foreach ($attempts as $attempt) {
        try {
            $payments->verify($attempt);
        } catch (\App\Modules\Booking\Domain\BookingFailure) {
            // Retry a later run without logging callback, authority or secrets.
        }
    }
    $this->info('تلاش‌های دارای شناسه درگاه بررسی شدند؛ نتیجه در مدیریت مالی قابل پیگیری است.');
});
Schedule::command('payments:reconcile')->everyFiveMinutes()->withoutOverlapping();
