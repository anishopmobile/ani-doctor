<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\OtpController;
use Illuminate\Support\Facades\Route;

Route::get('/', [\App\Http\Controllers\Web\WebsiteController::class, 'home'])->name('website.home');
Route::get('/sitemap.xml', \App\Http\Controllers\Web\SitemapController::class)->name('website.sitemap');
Route::get('/website-media/{media}', \App\Http\Controllers\Web\WebsiteMediaController::class)->whereNumber('media')->name('website.media.show');
Route::middleware(['auth', 'can:website.manage'])->group(function (): void {
    Route::post('/admin/website-media/{type}/{id}', [\App\Http\Controllers\Admin\WebsiteMediaController::class, 'store'])->whereIn('type', ['article', 'section'])->whereNumber('id')->name('website.media.store');
    Route::delete('/admin/website-media/{media}', [\App\Http\Controllers\Admin\WebsiteMediaController::class, 'destroy'])->whereNumber('media')->name('website.media.destroy');
});
Route::get('/articles', [\App\Http\Controllers\Web\PublicArticleController::class, 'index'])->name('website.articles.index');
Route::middleware(['auth', 'can:website.manage'])->prefix('admin/website-taxonomies')->group(function (): void {
    Route::get('/', [\App\Http\Controllers\Admin\WebsiteTaxonomyController::class, 'index'])->name('website.taxonomies.index');
    Route::post('/', [\App\Http\Controllers\Admin\WebsiteTaxonomyController::class, 'store'])->name('website.taxonomies.store');
    Route::delete('/{taxonomy}', [\App\Http\Controllers\Admin\WebsiteTaxonomyController::class, 'destroy'])->name('website.taxonomies.destroy');
});
Route::get('/articles/{slug}', [\App\Http\Controllers\Web\PublicArticleController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('website.articles.show');
Route::middleware(['auth', 'can:website.manage'])->prefix('admin/website-articles')->group(function (): void {
    Route::get('/', [\App\Http\Controllers\Admin\WebsiteArticleController::class, 'index'])->name('website.articles.manage');
    Route::post('/', [\App\Http\Controllers\Admin\WebsiteArticleController::class, 'store'])->name('website.articles.store');
    Route::put('/{article}', [\App\Http\Controllers\Admin\WebsiteArticleController::class, 'update'])->name('website.articles.update');
    Route::delete('/{article}', [\App\Http\Controllers\Admin\WebsiteArticleController::class, 'destroy'])->name('website.articles.destroy');
});
Route::get('/services', [\App\Http\Controllers\Web\PublicServiceController::class, 'index'])->name('website.services.index');
Route::get('/services/{service}', [\App\Http\Controllers\Web\PublicServiceController::class, 'show'])->whereNumber('service')->name('website.services.show');
Route::middleware(['auth', 'can:website.manage'])->prefix('admin/website-links')->group(function (): void {
    Route::get('/', [\App\Http\Controllers\Admin\WebsiteLinkController::class, 'index'])->name('website.links.index');
    Route::post('/', [\App\Http\Controllers\Admin\WebsiteLinkController::class, 'store'])->name('website.links.store');
    Route::put('/{link}', [\App\Http\Controllers\Admin\WebsiteLinkController::class, 'update'])->name('website.links.update');
    Route::delete('/{link}', [\App\Http\Controllers\Admin\WebsiteLinkController::class, 'destroy'])->name('website.links.destroy');
});
Route::middleware(['auth', 'can:website.manage'])->prefix('admin/website-sections')->group(function (): void {
    Route::get('/', [\App\Http\Controllers\Admin\WebsiteSectionController::class, 'index'])->name('website.sections.index');
    Route::post('/', [\App\Http\Controllers\Admin\WebsiteSectionController::class, 'store'])->name('website.sections.store');
    Route::put('/{section}', [\App\Http\Controllers\Admin\WebsiteSectionController::class, 'update'])->name('website.sections.update');
    Route::delete('/{section}', [\App\Http\Controllers\Admin\WebsiteSectionController::class, 'destroy'])->name('website.sections.destroy');
});
Route::middleware(['auth', \App\Http\Middleware\VerifiedBooker::class, 'throttle:reports'])->group(function (): void {
    Route::get('/management/reports', [\App\Http\Controllers\Admin\ReportController::class, 'index'])->name('reports.index');
    Route::post('/management/reports/staff-name', \App\Http\Controllers\Admin\StaffDisplayNameController::class)->middleware('can:reports.finance')->name('reports.staff-name');
    Route::get('/admin/discounts', [\App\Http\Controllers\Admin\DiscountController::class, 'index'])->middleware('can:discounts.manage')->name('discounts.index');
    Route::post('/admin/discounts', [\App\Http\Controllers\Admin\DiscountController::class, 'save'])->middleware('can:discounts.manage')->name('discounts.store');
    Route::post('/admin/discounts/{discount}', [\App\Http\Controllers\Admin\DiscountController::class, 'save'])->whereNumber('discount')->middleware('can:discounts.manage')->name('discounts.toggle');
});
Route::middleware([\App\Http\Middleware\VerifiedBooker::class, 'throttle:booking'])->group(function (): void {
    Route::post('/api/v1/discount-preview', \App\Http\Controllers\Web\DiscountPreviewController::class)->name('discounts.preview');
    Route::get('/appointments/{reference}/ticket', \App\Http\Controllers\Web\TicketController::class)->whereUuid('reference')->name('tickets.print');
    Route::get('/appointments/{reference}/ticket.pdf', \App\Http\Controllers\Web\TicketController::class)->whereUuid('reference')->name('tickets.pdf');
});
Route::middleware(['auth', \App\Http\Middleware\VerifiedBooker::class, 'throttle:desk', 'can:forms.manage'])->group(function (): void {
    Route::get('/admin/forms', [\App\Http\Controllers\Admin\FormBuilderController::class, 'index'])->name('forms.manage');
    Route::get('/admin/forms/new', [\App\Http\Controllers\Admin\FormBuilderController::class, 'edit'])->name('forms.new');
    Route::post('/admin/forms', [\App\Http\Controllers\Admin\FormBuilderController::class, 'save'])->name('forms.store');
    Route::get('/admin/forms/{form}', [\App\Http\Controllers\Admin\FormBuilderController::class, 'edit'])->whereNumber('form')->name('forms.edit');
    Route::post('/admin/forms/{form}', [\App\Http\Controllers\Admin\FormBuilderController::class, 'save'])->whereNumber('form')->name('forms.update');
});
Route::middleware(['auth', \App\Http\Middleware\VerifiedBooker::class, 'throttle:desk', 'can:notifications.manage'])->group(function (): void {
    Route::get('/admin/notifications', [\App\Http\Controllers\Admin\NotificationController::class, 'index'])->name('notifications.manage');
    Route::post('/admin/notifications', [\App\Http\Controllers\Admin\NotificationController::class, 'save'])->name('notifications.save');
});
Route::middleware([\App\Http\Middleware\VerifiedBooker::class, 'throttle:booking'])->group(function (): void {
    Route::get('/private-forms/{reference}', [\App\Http\Controllers\Web\PrivateFormController::class, 'index'])->whereUuid('reference')->name('private-forms.index');
    Route::get('/private-forms/{reference}/{form}', [\App\Http\Controllers\Web\PrivateFormController::class, 'show'])->whereUuid('reference')->whereNumber('form')->name('private-forms.show');
    Route::post('/private-forms/{reference}/{form}', [\App\Http\Controllers\Web\PrivateFormController::class, 'store'])->whereUuid('reference')->whereNumber('form')->name('private-forms.store');
});
Route::middleware(['auth', \App\Http\Middleware\VerifiedBooker::class, 'throttle:desk'])->group(function (): void {
    Route::get('/management/desk', [\App\Http\Controllers\Secretary\DeskController::class, 'index'])->middleware('can:desk.view')->name('desk');
    Route::post('/management/desk/search', [\App\Http\Controllers\Secretary\DeskController::class, 'filter'])->middleware('can:desk.view')->name('desk.filter');
    Route::get('/management/bookings/new', [\App\Http\Controllers\Secretary\DeskController::class, 'create'])->middleware('can:desk.book')->name('desk.create');
    Route::post('/management/bookings', [\App\Http\Controllers\Secretary\DeskController::class, 'store'])->middleware('can:desk.book', 'throttle:booking')->defaults('booking_action', 'create')->name('desk.store');
    Route::post('/management/appointments/{reference}/attendance', \App\Http\Controllers\Secretary\AttendanceController::class)->middleware('can:attendance.manage')->name('attendance.change');
    Route::post('/management/appointments/{reference}/collect-hold', [\App\Http\Controllers\Web\PaymentController::class, 'offline'])->middleware('can:payments.collect')->defaults('finance_action', 'collect_hold')->name('desk.collect-hold');
    Route::get('/management/patients', [\App\Http\Controllers\Secretary\PatientDirectoryController::class, 'index'])->middleware('can:patients.manage')->name('patients.index');
    Route::post('/management/patients/search', [\App\Http\Controllers\Secretary\PatientDirectoryController::class, 'search'])->defaults('directory_action', 'search')->middleware('can:patients.manage')->name('patients.search');
    Route::post('/management/patients', [\App\Http\Controllers\Secretary\PatientDirectoryController::class, 'save'])->middleware('can:patients.manage')->name('patients.store');
    Route::get('/management/patients/{patient}', [\App\Http\Controllers\Secretary\PatientDirectoryController::class, 'show'])->middleware('can:patients.manage')->name('patients.show');
    Route::put('/management/patients/{patient}', [\App\Http\Controllers\Secretary\PatientDirectoryController::class, 'save'])->middleware('can:patients.manage')->name('patients.update');
});
Route::match(['GET', 'POST'], '/payments/zarinpal/callback/{reference}', [\App\Http\Controllers\Web\PaymentController::class, 'callback'])->whereUuid('reference')->middleware('throttle:payment-callback')->name('payments.callback');
Route::get('/payments/local-simulator/{authority}', [\App\Http\Controllers\Web\LocalPaymentController::class, 'show'])->where('authority', 'SLOCAL[a-f0-9]{30}')->middleware('throttle:payments')->name('payments.local.show');
Route::post('/payments/local-simulator/{authority}', [\App\Http\Controllers\Web\LocalPaymentController::class, 'submit'])->where('authority', 'SLOCAL[a-f0-9]{30}')->middleware('throttle:payments')->name('payments.local.submit');
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
Route::get('/patient', DashboardController::class)->middleware(['auth', 'can:panel.patient'])->name('panel.patient');
foreach (['secretary', 'admin'] as $panel) {
    Route::get('/'.$panel, [\App\Http\Controllers\Secretary\DeskController::class, 'index'])->middleware(['auth', 'can:panel.'.$panel, \App\Http\Middleware\VerifiedBooker::class, 'can:desk.view', 'throttle:desk'])->name('panel.'.$panel);
}
