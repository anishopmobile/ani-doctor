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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
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
        $exceptions->dontFlash(['code', 'mobile']);
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
