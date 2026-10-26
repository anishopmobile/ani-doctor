<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestOtpRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Modules\Identity\Application\AuthService;
use App\Modules\Identity\Domain\OtpFailure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class OtpController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function requestCode(RequestOtpRequest $request, AuthService $service): RedirectResponse
    {
        $input = $request->validated();
        $challenge = $service->requestCode($input['mobile'], $input['purpose']);
        $request->session()->forget(['otp', 'guest_identity']);
        $request->session()->put('otp', [...$challenge, ...$input]);
        return redirect()->route('login')->with('status', 'کد تأیید ارسال شد.');
    }

    public function verify(VerifyOtpRequest $request, AuthService $service): RedirectResponse
    {
        $challenge = $request->session()->get('otp');
        if (!$challenge) {
            throw new OtpFailure('OTP_INVALID');
        }
        $user = $service->verify($challenge['mobile'], $challenge['id'], $challenge['purpose'], $request->validated('code'));
        $request->session()->forget(['otp', 'guest_identity']);
        if ($user !== null) {
            Auth::login($user);
        }
        $request->session()->regenerate();
        if ($user === null) {
            $request->session()->put('guest_identity', ['mobile' => $challenge['mobile'], 'expires_at' => now()->addSeconds(config('identity.guest_verification_seconds'))->timestamp]);
        }
        return redirect()->route('dashboard')->with('status', 'شماره موبایل تأیید شد.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
