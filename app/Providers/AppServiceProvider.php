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
        $this->app->bind(\App\Modules\Notifications\Domain\EventSmsProviderInterface::class, \App\Modules\Notifications\Infrastructure\SmsIrEventProvider::class);
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
        foreach (['forms.manage', 'forms.answers.view', 'forms.answers.write', 'notifications.manage', 'discounts.manage', 'reports.appointments', 'reports.finance', 'website.manage'] as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }
        \Illuminate\Support\Facades\View::composer('layouts.app', function ($view): void {
            $until = \Illuminate\Support\Facades\DB::table('payment_settings')->where('id', 1)->value('emergency_until');
            $view->with('clinicEmergencyActive', $until && now()->lessThan($until));
        });
        \Illuminate\Support\Facades\View::composer(['website.home', 'website.layout', 'website.pages.show'], function ($view): void {
            $themes = app(\App\Modules\Website\Application\ThemeService::class);
            $view->with('websiteTheme', $themes->settings())->with('websiteLogo', $themes->publishedLogo());
            $links = app(\App\Modules\Website\Application\LinkService::class);
            $view->with('headerLinks', $links->published('header'))->with('footerLinks', $links->published('footer'));
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
        RateLimiter::for('website-editor', fn (Request $request) => Limit::perMinute(60)->by('website:'.($request->user()?->id ?? $request->ip())));
        RateLimiter::for('calendar', fn (Request $request) => Limit::perMinute(60)->by('calendar:'.$request->ip()));
        RateLimiter::for('booking', fn (Request $request) => Limit::perMinute(20)->by('booking:'.$request->ip()));
        RateLimiter::for('payments', fn (Request $request) => Limit::perMinute(20)->by('payments:'.$request->ip()));
        RateLimiter::for('payment-callback', fn (Request $request) => Limit::perMinute(60)->by('callback:'.$request->ip()));
        RateLimiter::for('desk', fn (Request $request) => Limit::perMinute(60)->by('desk:'.($request->user()?->id ?? $request->ip())));
        RateLimiter::for('reports', fn (Request $request) => Limit::perMinute($request->filled('format') ? 5 : 30)->by('reports:'.($request->user()?->id ?? $request->ip()).':'.($request->filled('format') ? 'export' : 'view')));
    }
}
