<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\SecurityHeaders;
use App\Modules\Identity\Domain\OtpFailure;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->redirectUsersTo('/dashboard');
        $middleware->validateCsrfTokens(except: ['payments/zarinpal/callback/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (\Illuminate\Database\QueryException $error) {
            if (request()->is('appointments*', 'api/v1/appointments*', 'payments*', 'api/v1/payments*', 'wallet*', 'admin/finance*', 'admin/forms*', 'admin/notifications*', 'private-forms*', 'management/desk*', 'management/bookings*', 'management/patients*', 'management/appointments*', 'secretary', 'admin', 'dashboard', 'patient')) {
                \Illuminate\Support\Facades\Log::error('booking.database_error', ['sql_state' => $error->errorInfo[0] ?? 'unknown']);
                return false;
            }
        });
        $exceptions->render(function (\Illuminate\Database\QueryException $error, Request $request) {
            if ($request->is('appointments*', 'api/v1/appointments*', 'payments*', 'api/v1/payments*', 'wallet*', 'admin/finance*', 'admin/forms*', 'admin/notifications*', 'private-forms*', 'management/desk*', 'management/bookings*', 'management/patients*', 'management/appointments*', 'secretary', 'admin', 'dashboard', 'patient')) {
                $message = 'ثبت یا دریافت نوبت فعلاً انجام نشد. همان درخواست را دوباره بررسی کنید.';
                if ($request->expectsJson() || $request->is('api/v1/*')) {
                    return response()->json(['success' => false,'data' => null,'error' => ['code' => 'BOOKING_TEMPORARILY_UNAVAILABLE','message' => $message],'meta' => (object)[]], 503);
                }
                return response()->view('errors.message', ['message' => $message], 503);
            }
        });
        $exceptions->dontReport([\App\Modules\Booking\Domain\BookingFailure::class]);
        $exceptions->render(function (\App\Modules\Booking\Domain\BookingFailure $error, Request $request) {
            $message = match ($error->errorCode) {
                'VERIFICATION_REQUIRED' => 'ابتدا با OTP وارد شوید یا شماره مهمان را تأیید کنید.',
                'SLOT_UNAVAILABLE' => 'این ساعت دیگر آزاد نیست. ساعت دیگری انتخاب کنید؛ اطلاعات فرم حفظ شده است.',
                'PRICE_CHANGED' => 'تعرفه تغییر کرده است. صفحه را تازه کنید و مبلغ جدید را بررسی کنید.',
                'HOLD_EXPIRED' => 'مهلت رزرو موقت تمام شده است؛ دوباره از تقویم زمان انتخاب کنید.',
                'BOOKING_LIMIT_REACHED' => 'سقف نوبت فعال بیمار یا رزروکننده تکمیل شده است.',
                'EDIT_WINDOW_CLOSED' => 'مهلت تغییر یا لغو این نوبت گذشته است؛ با مطب تماس بگیرید.',
                'STALE_APPOINTMENT' => 'این نوبت تغییر کرده است؛ صفحه را تازه کنید.',
                'IDEMPOTENCY_CONFLICT' => 'این درخواست قبلاً برای اقدام دیگری استفاده شده است؛ صفحه را تازه کنید.',
                'FINANCIAL_SETTLEMENT_REQUIRED' => 'لغو این نوبت نیازمند تسویه مالی است؛ با مطب تماس بگیرید.',
                'GATEWAY_UNCERTAIN' => 'نتیجه درگاه هنوز مشخص نیست؛ از پرداخت تکراری خودداری کنید و وضعیت را پیگیری کنید.',
                'GATEWAY_DISABLED' => 'پرداخت آنلاین فعلاً فعال نیست؛ کمی بعد دوباره بررسی کنید.',
                'PAYMENT_IN_PROGRESS' => 'یک تلاش پرداخت باز وجود دارد؛ ابتدا وضعیت همان پرداخت را پیگیری کنید.',
                'INSUFFICIENT_CREDIT' => 'اعتبار قابل مصرف کافی نیست.',
                'EMERGENCY_DISABLED' => 'حالت پرداخت در مطب فعال نیست یا مهلت آن گذشته است.',
                'ATTENDANCE_INVALID_STATE' => 'این تغییر حضور با وضعیت یا زمان نوبت سازگار نیست؛ صفحه را تازه کنید.',
                'ATTENDANCE_IN_PROGRESS' => 'برای این نوبت مراجعه ثبت شده است؛ ابتدا وضعیت حضور را بررسی کنید.',
                'URGENT_NOT_ALLOWED' => 'نوبت فوری به مجوز، دلیل و ساعت آینده در شیفت باز امروز نیاز دارد.',
                'SERVICE_UNAVAILABLE' => 'خدمت در دسترس نیست.',
                default => 'این اقدام مجاز نیست یا وضعیت نوبت اجازه آن را نمی‌دهد.',
            };
            if ($request->expectsJson() || $request->is('api/v1/*')) {
                return response()->json(['success' => false,'data' => null,'error' => ['code' => $error->errorCode,'message' => $message],'meta' => ['server_now' => now()->toIso8601String()]], $error->status);
            }
            if ($error->status === 401) {
                return redirect()->route('login')->withErrors(['booking' => $message]);
            }
            if ($error->status === 404) {
                abort(404);
            }
            if ($error->status === 403) {
                return response()->view('errors.403', [], 403);
            }
            if ($request->is('payments/zarinpal/callback/*')) {
                return response()->view('errors.message', ['message' => $message], $error->status);
            }
            return back()->withInput($request->except('_token', 'Authority', 'Status', 'answers', 'schema'))->withErrors(['booking' => $message]);
        });
        $exceptions->shouldRenderJsonWhen(fn (Request $request, \Throwable $e) => $request->is('api/v1/*') || $request->expectsJson());
        $exceptions->render(function (\Illuminate\Validation\ValidationException $error, Request $request) {
            if ($request->is('api/v1/*')) {
                return response()->json(['success' => false, 'data' => null, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'ورودی معتبر نیست.', 'details' => $error->errors()], 'meta' => (object) []], 422);
            }
        });
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $error, Request $request) {
            if ($request->is('api/v1/*') && $error->getStatusCode() === 404) {
                return response()->json(['success' => false, 'data' => null, 'error' => ['code' => 'NOT_FOUND', 'message' => 'خدمت در دسترس نیست.'], 'meta' => (object) []], 404);
            }
        });
        $exceptions->dontFlash(['code', 'mobile', 'Authority', 'Status', 'query', 'answers', 'schema']);
        $exceptions->dontReport([OtpFailure::class]);
        $exceptions->render(function (OtpFailure $error, Request $request) {
            $message = match ($error->errorCode) {
                'SMS_UNAVAILABLE' => 'ارسال پیامک در دسترس نیست. کمی بعد دوباره تلاش کنید.',
                'OTP_COOLDOWN' => 'برای ارسال دوباره کد، یک دقیقه صبر کنید.',
                default => 'کد معتبر نیست یا مهلت آن تمام شده است. دوباره تلاش کنید.',
            };
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'data' => null, 'error' => ['code' => $error->errorCode, 'message' => $message], 'meta' => (object) []], $error->status);
            }
            return back()->withErrors(['code' => $message]);
        });
        $exceptions->render(function (ThrottleRequestsException $error, Request $request) {
            $message = 'تعداد درخواست‌ها زیاد است. کمی بعد دوباره تلاش کنید.';
            if ($request->expectsJson() || $request->is('api/v1/*')) {
                return response()->json(['success' => false, 'data' => null, 'error' => ['code' => 'RATE_LIMITED', 'message' => $message], 'meta' => (object) []], 429, $error->getHeaders());
            }
            return response()->view('errors.message', ['message' => $message], 429, $error->getHeaders());
        });
    })->create();
