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

Artisan::command('notifications:dispatch', function (\App\Modules\Notifications\Application\MessageDispatcher $dispatcher): void {
    $dispatcher->run();
    $this->info('صف اعلان‌ها بررسی شد؛ پذیرش ارائه‌دهنده به معنی تحویل به گوشی نیست.');
});
Schedule::command('notifications:dispatch')->everyMinute()->withoutOverlapping();
Artisan::command('notifications:delivery', function (\App\Modules\Notifications\Application\MessageDispatcher $dispatcher): void {
    $dispatcher->poll();
});
Schedule::command('notifications:delivery')->everyFifteenMinutes()->withoutOverlapping();

Artisan::command('ops:check', function (\App\Modules\Operations\Application\ReadinessService $readiness): int {
    $result = $readiness->inspect();
    $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));
    return $result['ready'] ? 0 : 1;
})->purpose('Read-only local readiness checks; never certifies a remote host or provider');

Artisan::command('ops:heartbeat', function (): void {
    \Illuminate\Support\Facades\Cache::put('operations.scheduler_seen', time(), 600);
})->purpose('Record scheduler liveness without patient information');
Schedule::command('ops:heartbeat')->everyMinute()->withoutOverlapping();

Artisan::command('ops:backup-key {path}', function (\App\Modules\Operations\Infrastructure\PrivatePaths $paths): int {
    $mask = umask(0077);
    try {
        $file = $paths->directory(dirname($this->argument('path'))).'/'.basename($this->argument('path'));
        if (file_exists($file) || $paths->inside($file, storage_path('app/private'))) {
            throw new \RuntimeException('KEY_PATH_INVALID');
        }
        $handle = fopen($file, 'xb');
        chmod($file, 0600);
        try {
            app(\App\Modules\Operations\Infrastructure\EncryptedArchive::class)->put($handle, base64_encode(random_bytes(32))."\n");
        } finally {
            fclose($handle);
        }
        $this->info('کلید مستقل ساخته شد؛ مقدار آن چاپ نشده است. آن را جدا از بکاپ و APP_KEY نگه دارید.');
        return 0;
    } catch (\Throwable) {
        $this->error('کلید ساخته نشد؛ مسیر خصوصی و نبود فایل قبلی را بررسی کنید.');
        return 1;
    } finally {
        umask($mask);
    }
})->purpose('Create a separate backup key with owner-only file permissions; never replaces an existing key');

Artisan::command('ops:backup {--writers-stopped}', function (\App\Modules\Operations\Application\BackupService $backups): int {
    try {
        $result = $backups->create((bool) $this->option('writers-stopped'));
        $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));
        return 0;
    } catch (\Throwable) {
        $this->error('بکاپ ساخته نشد؛ کلید مستقل، مسیر خصوصی، maintenance و توقف همه نویسنده‌ها را بررسی کنید.');
        return 1;
    }
})->purpose('Encrypted MySQL/private-file backup during a controlled maintenance window');

Artisan::command('ops:restore {archive} {target} {private-target} {--isolated-target}', function (\App\Modules\Operations\Application\BackupService $backups): int {
    if (!$this->option('isolated-target')) {
        $this->error('بازیابی فقط با تأیید محیط جدا و دیتابیس خالی ani_doctor_restore_* مجاز است.');
        return 1;
    }
    try {
        $result = $backups->restore($this->argument('archive'), $this->argument('target'), $this->argument('private-target'));
        $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));
        return 0;
    } catch (\Throwable) {
        $this->error('بازیابی تأیید نشد؛ مقصد جدا، کلیدها و صحت بکاپ را بررسی کنید. مقصد ناموفق را فعال نکنید.');
        return 1;
    }
})->purpose('Authenticate an entire backup before restoring to an empty isolated database; never promotes it');
