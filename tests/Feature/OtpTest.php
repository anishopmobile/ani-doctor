<?php

namespace Tests\Feature;

use App\Modules\Identity\Application\AuthService;
use App\Modules\Identity\Domain\OtpFailure;
use App\Modules\Identity\Domain\SmsProviderInterface;
use App\Modules\Identity\Infrastructure\OtpChallenge;
use App\Modules\Identity\Infrastructure\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FakeSmsProvider;
use Tests\TestCase;

final class OtpTest extends TestCase
{
    use RefreshDatabase;

    private FakeSmsProvider $sms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->sms = new FakeSmsProvider();
        $this->app->instance(SmsProviderInterface::class, $this->sms);
    }

    public function test_login_normalizes_digits_hashes_code_and_only_grants_patient(): void
    {
        $this->post('/otp/request', ['mobile' => '۰۹۱۲۳۴۵۶۷۸۹', 'purpose' => 'login', 'role' => 'admin'])->assertRedirect('/login');
        $challenge = OtpChallenge::firstOrFail();
        $this->assertTrue(Hash::check($this->sms->lastCode(), $challenge->code_hash));
        $this->assertNotSame($this->sms->lastCode(), $challenge->code_hash);
        $this->post('/otp/verify', ['code' => $this->sms->lastCode(), 'role' => 'admin'])->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $user = User::sole();
        $this->assertTrue($user->hasPermission('panel.patient'));
        $this->assertFalse($user->hasPermission('panel.admin'));
        $this->assertNull($challenge->fresh()->code_hash);
        $this->assertDatabaseCount('patients', 0);
        $this->assertDatabaseHas('identity_audit_events', ['user_id' => $user->id, 'event' => 'otp_login']);
    }

    public function test_guest_verification_creates_no_account_and_expires(): void
    {
        $this->post('/otp/request', ['mobile' => '09123456789', 'purpose' => 'guest']);
        $this->post('/otp/verify', ['code' => $this->sms->lastCode()])->assertRedirect('/dashboard')->assertSessionHas('guest_identity');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->get('/dashboard')->assertOk();
        $this->get('/admin')->assertRedirect('/login');
        $this->travel(600)->seconds();
        $this->get('/dashboard')->assertForbidden();
    }

    public function test_fifth_wrong_attempt_locks_code_and_attempts_are_committed(): void
    {
        $service = app(AuthService::class);
        $challenge = $service->requestCode('09123456789', 'login');
        $wrong = $this->sms->lastCode() === '111111' ? '222222' : '111111';
        for ($i = 0; $i < 5; $i++) {
            $this->assertInvalid(fn () => $service->verify('09123456789', $challenge['id'], 'login', $wrong));
        }
        $this->assertSame(5, OtpChallenge::sole()->attempts);
        $this->assertSame('locked', OtpChallenge::sole()->status);
        $this->assertInvalid(fn () => $service->verify('09123456789', $challenge['id'], 'login', $this->sms->lastCode()));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_code_expires_at_exact_server_deadline(): void
    {
        $service = app(AuthService::class);
        $challenge = $service->requestCode('09123456789', 'login');
        $this->travel(120)->seconds();
        $this->assertInvalid(fn () => $service->verify('09123456789', $challenge['id'], 'login', $this->sms->lastCode()));
    }

    public function test_code_can_only_be_consumed_once(): void
    {
        $service = app(AuthService::class);
        $challenge = $service->requestCode('09123456789', 'login');
        $service->verify('09123456789', $challenge['id'], 'login', $this->sms->lastCode());
        $this->assertInvalid(fn () => $service->verify('09123456789', $challenge['id'], 'login', $this->sms->lastCode()));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('identity_audit_events', 1);
    }

    public function test_resend_invalidates_previous_challenge_and_cooldown_is_enforced(): void
    {
        $service = app(AuthService::class);
        $first = $service->requestCode('09123456789', 'login');
        $oldCode = $this->sms->lastCode();
        try {
            $service->requestCode('09123456789', 'guest');
            $this->fail('Cooldown was bypassed');
        } catch (OtpFailure $error) {
            $this->assertSame('OTP_COOLDOWN', $error->errorCode);
        }
        $this->travel(60)->seconds();
        $second = $service->requestCode('09123456789', 'login');
        $this->assertNotSame($first['id'], $second['id']);
        $this->assertInvalid(fn () => $service->verify('09123456789', $first['id'], 'login', $oldCode));
        $service->verify('09123456789', $second['id'], 'login', $this->sms->lastCode());
    }

    public function test_session_and_purpose_cannot_be_forged(): void
    {
        $service = app(AuthService::class);
        $challenge = $service->requestCode('09123456789', 'guest');
        $this->assertInvalid(fn () => $service->verify('09123456789', $challenge['id'], 'login', $this->sms->lastCode()));
        $this->postJson('/otp/verify', ['code' => $this->sms->lastCode(), 'mobile' => '09123456789', 'id' => $challenge['id']])->assertUnprocessable();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_sms_outage_fails_closed_without_leaking_provider_details(): void
    {
        $this->sms->fail = true;
        $response = $this->postJson('/otp/request', ['mobile' => '09123456789', 'purpose' => 'login']);
        $response->assertStatus(503)->assertJsonPath('error.code', 'SMS_UNAVAILABLE');
        $this->assertStringNotContainsString('Provider body', $response->getContent());
        $this->assertNull(OtpChallenge::sole()->code_hash);
        $this->assertSame('failed', OtpChallenge::sole()->status);
        $this->assertGuest();
    }

    public function test_disabled_user_cannot_login(): void
    {
        $user = User::create(['mobile' => '09123456789']);
        $user->forceFill(['is_active' => false])->save();
        $this->post('/otp/request', ['mobile' => $user->mobile, 'purpose' => 'login']);
        $this->postJson('/otp/verify', ['code' => $this->sms->lastCode()])->assertUnprocessable();
        $this->assertGuest();
        $this->assertSame('consumed', OtpChallenge::sole()->status);
    }

    public function test_mobile_hourly_and_ip_minute_limits(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/otp/request', ['mobile' => '09123456789', 'purpose' => 'login'])->assertRedirect();
            $this->travel(61)->seconds();
        }
        $this->postJson('/otp/request', ['mobile' => '۰۹۱۲۳۴۵۶۷۸۹', 'purpose' => 'login'])->assertStatus(429);
        $this->travel(61)->minutes();
        for ($i = 0; $i < 10; $i++) {
            $this->post('/otp/request', ['mobile' => '093000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'purpose' => 'guest']);
        }
        $this->postJson('/otp/request', ['mobile' => '09300000111', 'purpose' => 'guest'])->assertStatus(429);
    }

    public function test_verification_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/otp/verify', ['code' => '123456'])->assertUnprocessable();
        }
        $this->postJson('/otp/verify', ['code' => '123456'])->assertStatus(429);
    }

    public function test_invalid_mobile_is_rejected_before_sms(): void
    {
        foreach (['0912', '+989123456789', 'test', ['mobile']] as $mobile) {
            $this->postJson('/otp/request', ['mobile' => $mobile, 'purpose' => 'login'])->assertUnprocessable();
        }
        $this->assertSame([], $this->sms->messages);
    }

    public function test_logout_invalidates_identity_and_csrf_token(): void
    {
        $this->post('/otp/request', ['mobile' => '09123456789', 'purpose' => 'login']);
        $this->post('/otp/verify', ['code' => $this->sms->lastCode()]);
        $token = session()->token();
        $this->post('/logout')->assertRedirect('/login')->assertSessionMissing('guest_identity')->assertSessionMissing('otp');
        $this->assertGuest();
        $this->assertNotSame($token, session()->token());
    }

    private function assertInvalid(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected OTP_INVALID');
        } catch (OtpFailure $error) {
            $this->assertSame('OTP_INVALID', $error->errorCode);
        }
    }
}
