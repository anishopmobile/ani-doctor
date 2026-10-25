<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\OtpController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');
Route::match(['GET', 'POST'], '/payments/zarinpal/callback/{reference}', [\App\Http\Controllers\Web\PaymentController::class, 'callback'])->whereUuid('reference')->middleware('throttle:payment-callback')->name('payments.callback');
Route::middleware([\App\Http\Middleware\VerifiedBooker::class, 'throttle:payments'])->group(function (): void {
    Route::get('/wallet', [\App\Http\Controllers\Web\WalletController::class, 'index'])->name('wallet');
    Route::post('/wallet/refunds', [\App\Http\Controllers\Web\WalletController::class, 'refund'])->defaults('finance_action', 'refund')->name('wallet.refund');
    Route::post('/payments/{reference}/initiate', [\App\Http\Controllers\Web\PaymentController::class, 'initiate'])->defaults('finance_action', 'initiate')->name('payments.initiate');
    Route::post('/api/v1/payments/{reference}/initiate', [\App\Http\Controllers\Web\PaymentController::class, 'initiate'])->defaults('finance_action', 'initiate');
    Route::post('/payments/{reference}/emergency', [\App\Http\Controllers\Web\PaymentController::class, 'offline'])->defaults('finance_action', 'emergency')->name('payments.emergency');
    Route::post('/payments/{reference}/collect', [\App\Http\Controllers\Web\PaymentController::class, 'offline'])->defaults('finance_action', 'collect')->middleware('auth', 'can:payments.collect')->name('payments.collect');
});
Route::middleware([\App\Http\Middleware\VerifiedBooker::class, 'auth', 'can:payments.review', 'throttle:payments'])->group(function (): void {
    Route::get('/admin/finance', [\App\Http\Controllers\Admin\FinanceController::class, 'index'])->name('finance');
    Route::put('/admin/finance/emergency', [\App\Http\Controllers\Admin\FinanceController::class, 'settings'])->defaults('finance_action', 'settings')->middleware('can:payments.settings')->name('finance.settings');
    Route::post('/admin/finance/refunds/{reference}', [\App\Http\Controllers\Admin\FinanceController::class, 'decision'])->defaults('finance_action', 'decision')->middleware('can:refunds.manage')->name('finance.refund');
    Route::post('/admin/finance/reconcile/{reference}', [\App\Http\Controllers\Admin\FinanceController::class, 'reconcile'])->name('finance.reconcile');
});
Route::middleware([\App\Http\Middleware\VerifiedBooker::class, 'throttle:booking'])->group(function (): void {
    Route::get('/appointments', [\App\Http\Controllers\Web\AppointmentController::class, 'index'])->name('appointments.index');
    Route::get('/appointments/{reference}', [\App\Http\Controllers\Web\AppointmentController::class, 'show'])->name('appointments.show');
    Route::get('/appointments/{reference}/edit', [\App\Http\Controllers\Web\AppointmentController::class, 'edit'])->name('appointments.edit');
    Route::get('/api/v1/appointments/{reference}/calendar', [\App\Http\Controllers\Web\CalendarController::class, 'month'])->name('appointments.calendar.month');
    Route::get('/api/v1/appointments/{reference}/availability', [\App\Http\Controllers\Web\CalendarController::class, 'day'])->name('appointments.calendar.day');
    Route::post('/appointments', [\App\Http\Controllers\Web\AppointmentController::class, 'store'])->defaults('booking_action', 'create')->name('appointments.store');
    Route::post('/api/v1/appointments', [\App\Http\Controllers\Web\AppointmentController::class, 'store'])->defaults('booking_action', 'create')->name('api.appointments.store');
    foreach (['reschedule','cancel','claim'] as $action) {
        Route::post('/appointments/{reference}/'.$action, [\App\Http\Controllers\Web\AppointmentController::class, 'change'])->defaults('booking_action', $action)->name('appointments.'.$action);
        Route::match($action === 'reschedule' ? ['POST','PATCH'] : ['POST'], '/api/v1/appointments/{reference}/'.$action, [\App\Http\Controllers\Web\AppointmentController::class, 'change'])->defaults('booking_action', $action)->name('api.appointments.'.$action);
    }
});
Route::middleware(['auth','can:booking.settings'])->group(function (): void {
    Route::get('/admin/booking-settings', [\App\Http\Controllers\Admin\BookingSettingsController::class,'index'])->name('booking.settings');
    Route::put('/admin/booking-settings', [\App\Http\Controllers\Admin\BookingSettingsController::class,'update'])->name('booking.settings.update');
});
Route::get('/calendar', [\App\Http\Controllers\Web\CalendarController::class, 'index'])->name('calendar');
Route::middleware('throttle:calendar')->group(function (): void {
    Route::get('/api/v1/calendar', [\App\Http\Controllers\Web\CalendarController::class, 'month'])->name('calendar.month');
    Route::get('/api/v1/availability', [\App\Http\Controllers\Web\CalendarController::class, 'day'])->name('calendar.day');
});
Route::middleware(['auth', 'can:catalog.manage'])->group(function (): void {
    Route::get('/admin/services', [\App\Http\Controllers\Admin\ServiceController::class, 'index'])->name('services.index');
    Route::post('/admin/services', [\App\Http\Controllers\Admin\ServiceController::class, 'store'])->name('services.store');
    Route::put('/admin/services/{service}', [\App\Http\Controllers\Admin\ServiceController::class, 'update'])->name('services.update');
    Route::delete('/admin/services/{service}', [\App\Http\Controllers\Admin\ServiceController::class, 'destroy'])->name('services.destroy');
});
Route::middleware(['auth', 'can:schedules.manage'])->group(function (): void {
    Route::get('/management/schedule', [\App\Http\Controllers\Admin\ScheduleController::class, 'index'])->name('schedule.index');
    Route::post('/management/schedule/weekly', [\App\Http\Controllers\Admin\ScheduleController::class, 'weekly'])->name('schedule.weekly');
    Route::post('/management/schedule/exceptions', [\App\Http\Controllers\Admin\ScheduleController::class, 'exception'])->name('schedule.exception');
    Route::delete('/management/schedule/exceptions/{exception}', [\App\Http\Controllers\Admin\ScheduleController::class, 'destroy'])->name('schedule.destroy');
    Route::put('/management/schedule/horizon', [\App\Http\Controllers\Admin\ScheduleController::class, 'horizon'])->middleware('can:schedules.settings')->name('schedule.horizon');
});
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [OtpController::class, 'show'])->name('login');
    Route::post('/otp/request', [OtpController::class, 'requestCode'])->middleware('throttle:otp-send')->name('otp.request');
    Route::post('/otp/verify', [OtpController::class, 'verify'])->middleware('throttle:otp-verify')->name('otp.verify');
});
Route::post('/logout', [OtpController::class, 'logout'])->name('logout');
Route::get('/dashboard', DashboardController::class)->name('dashboard');
foreach (['patient', 'secretary', 'admin'] as $panel) {
    Route::view('/'.$panel, 'panel', ['panel' => $panel])->middleware(['auth', 'can:panel.'.$panel])->name('panel.'.$panel);
}
