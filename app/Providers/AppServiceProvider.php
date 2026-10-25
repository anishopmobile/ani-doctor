<?php

namespace App\Providers;

use App\Modules\Identity\Domain\MobileNumber;
use App\Modules\Identity\Domain\SmsProviderInterface;
use App\Modules\Identity\Infrastructure\SmsIrProvider;
use App\Modules\Identity\Infrastructure\User;
use App\Modules\Patients\Infrastructure\Patient;
use App\Policies\PatientPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsProviderInterface::class, SmsIrProvider::class);
        $this->app->bind(\App\Modules\Scheduling\Domain\BookingReadInterface::class, \App\Modules\Booking\Infrastructure\AppointmentReader::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Patient::class, PatientPolicy::class);
        Gate::policy(\App\Modules\Booking\Infrastructure\Appointment::class, \App\Policies\AppointmentPolicy::class);
        foreach (['patient', 'secretary', 'admin'] as $panel) {
            Gate::define('panel.'.$panel, fn (User $user) => $user->hasPermission('panel.'.$panel));
        }
        RateLimiter::for('otp-send', function (Request $request): array {
            $mobile = is_string($request->input('mobile')) ? MobileNumber::normalize($request->input('mobile')) : '';
            $key = hash_hmac('sha256', $mobile, (string) config('app.key'));
            return [Limit::perMinute(10)->by('ip:'.$request->ip()), Limit::perHour(5)->by('mobile:'.$key)];
        });
        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinute(20)->by('verify:'.$request->ip()));
        foreach (['catalog.manage', 'schedules.manage', 'schedules.settings', 'appointments.manage', 'booking.settings'] as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }
        RateLimiter::for('calendar', fn (Request $request) => Limit::perMinute(60)->by('calendar:'.$request->ip()));
        RateLimiter::for('booking', fn (Request $request) => Limit::perMinute(20)->by('booking:'.$request->ip()));
    }
}
