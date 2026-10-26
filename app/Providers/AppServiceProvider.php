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
        $this->app->bind(\App\Modules\Payments\Domain\PaymentGatewayInterface::class, \App\Modules\Payments\Infrastructure\ZarinpalGateway::class);
        $this->app->bind(\App\Modules\Booking\Domain\BookingFinanceInterface::class, \App\Modules\Billing\Application\BookingFinance::class);
        $this->app->bind(SmsProviderInterface::class, SmsIrProvider::class);
        $this->app->bind(\App\Modules\Scheduling\Domain\BookingReadInterface::class, \App\Modules\Booking\Infrastructure\AppointmentReader::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\View::composer('layouts.app', function ($view): void {
            $until = \Illuminate\Support\Facades\DB::table('payment_settings')->where('id', 1)->value('emergency_until');
            $view->with('clinicEmergencyActive', $until && now()->lessThan($until));
        });
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
        foreach (['catalog.manage', 'schedules.manage', 'schedules.settings', 'appointments.manage', 'appointments.override_capacity', 'appointments.override_window', 'booking.settings', 'payments.settings', 'payments.collect', 'refunds.manage', 'payments.review', 'desk.view', 'desk.book', 'appointments.urgent', 'attendance.manage', 'patients.manage'] as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }
        RateLimiter::for('calendar', fn (Request $request) => Limit::perMinute(60)->by('calendar:'.$request->ip()));
        RateLimiter::for('booking', fn (Request $request) => Limit::perMinute(20)->by('booking:'.$request->ip()));
        RateLimiter::for('payments', fn (Request $request) => Limit::perMinute(20)->by('payments:'.$request->ip()));
        RateLimiter::for('payment-callback', fn (Request $request) => Limit::perMinute(60)->by('callback:'.$request->ip()));
        RateLimiter::for('desk', fn (Request $request) => Limit::perMinute(60)->by('desk:'.($request->user()?->id ?? $request->ip())));
    }
}
