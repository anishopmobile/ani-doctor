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
