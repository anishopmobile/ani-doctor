<?php

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\MobileNumber;
use App\Modules\Identity\Domain\OtpFailure;
use App\Modules\Identity\Domain\SmsProviderInterface;
use App\Modules\Identity\Infrastructure\OtpChallenge;
use App\Modules\Identity\Infrastructure\Role;
use App\Modules\Identity\Infrastructure\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class AuthService
{
    public function __construct(private readonly SmsProviderInterface $sms, private readonly LocalOtpPreview $preview)
    {
    }

    /** @return array{id: string, expires_at: string, retry_after: int, preview_code?: string} */
    public function requestCode(string $mobile, string $purpose): array
    {
        if (!MobileNumber::isValid($mobile) || !in_array($purpose, ['login', 'guest'], true)) {
            throw new OtpFailure('VALIDATION_ERROR');
        }
        $code = (string) random_int(100000, 999999);
        $challenge = DB::transaction(function () use ($mobile, $purpose, $code): OtpChallenge {
            DB::table('otp_challenges')->insertOrIgnore(['mobile' => $mobile]);
            $challenge = OtpChallenge::whereKey($mobile)->lockForUpdate()->firstOrFail();
            if ($challenge->sent_at?->addSeconds(config('identity.resend_seconds'))->isFuture()) {
                throw new OtpFailure('OTP_COOLDOWN', 429);
            }
            $challenge->fill([
                'challenge_id' => (string) Str::uuid(), 'purpose' => $purpose,
                'code_hash' => Hash::make($code), 'status' => 'sending', 'attempts' => 0,
                'expires_at' => now()->addSeconds(config('identity.otp_ttl_seconds')),
                'sent_at' => now(), 'consumed_at' => null,
            ])->save();
            return $challenge;
        }, 3);

        // Network calls stay outside database locks. Never report provider exceptions:
        // their request/response bodies may contain the OTP or API key.
        $preview = $this->preview->enabled();
        try {
            if (!$preview) {
                $this->sms->sendOtp($mobile, $code);
            }
        } catch (\Throwable) {
            OtpChallenge::whereKey($mobile)->where('challenge_id', $challenge->challenge_id)
                ->update(['status' => 'failed', 'code_hash' => null]);
            throw new OtpFailure('SMS_UNAVAILABLE', 503);
        }
        OtpChallenge::whereKey($mobile)->where('challenge_id', $challenge->challenge_id)->where('status', 'sending')
            ->update(['status' => 'sent']);

        $result = ['id' => $challenge->challenge_id, 'expires_at' => $challenge->expires_at->toIso8601String(), 'retry_after' => config('identity.resend_seconds')];
        if ($preview) {
            $result['preview_code'] = $code;
        }
        return $result;
    }

    public function verify(string $mobile, string $challengeId, string $purpose, #[\SensitiveParameter] string $code): ?User
    {
        $result = DB::transaction(function () use ($mobile, $challengeId, $purpose, $code): User|string|null {
            $challenge = OtpChallenge::whereKey($mobile)->lockForUpdate()->first();
            if (!$challenge || $challenge->challenge_id !== $challengeId || $challenge->purpose !== $purpose
                || $challenge->status !== 'sent' || !$challenge->expires_at?->isFuture()
                || $challenge->attempts >= config('identity.max_attempts')) {
                return 'OTP_INVALID';
            }
            $challenge->attempts++;
            if (!Hash::check($code, $challenge->code_hash)) {
                if ($challenge->attempts >= config('identity.max_attempts')) {
                    $challenge->status = 'locked';
                    $challenge->code_hash = null;
                }
                $challenge->save();
                return 'OTP_INVALID';
            }
            $challenge->fill(['status' => 'consumed', 'consumed_at' => now(), 'code_hash' => null])->save();
            if ($purpose === 'guest') {
                return null;
            }
            $user = User::firstOrCreate(['mobile' => $mobile]);
            if (!$user->is_active) {
                return 'OTP_INVALID';
            }
            $user->forceFill(['mobile_verified_at' => now()])->save();
            if (!$user->roles()->exists()) {
                $user->roles()->attach(Role::where('name', 'patient')->sole());
            }
            DB::table('identity_audit_events')->insert(['user_id' => $user->id, 'event' => 'otp_login', 'created_at' => now()]);
            return $user;
        }, 3);

        // Throw after commit so failed attempts and consumption are never rolled back.
        if (is_string($result)) {
            throw new OtpFailure($result);
        }
        return $result;
    }
}
