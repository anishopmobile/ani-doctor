<?php

namespace Tests\Feature;

use App\Modules\Identity\Domain\SmsProviderInterface;
use App\Modules\Identity\Infrastructure\OtpChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FakeSmsProvider;
use Tests\TestCase;

final class LocalOtpPreviewTest extends TestCase
{
    use RefreshDatabase;

    private FakeSmsProvider $sms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->app->instance('env', 'local');
        config(['app.url' => 'http://localhost', 'identity.local_otp_preview' => true]);
        \Illuminate\Support\Facades\URL::forceRootUrl('http://localhost');
        \Illuminate\Support\Facades\URL::forceScheme('http');
        $token = str_repeat('a', 40);
        $this->withSession(['_token' => $token])->withHeaders(['X-CSRF-TOKEN' => $token]);
        $this->sms = new FakeSmsProvider();
        $this->app->instance(SmsProviderInterface::class, $this->sms);
    }

    public function test_preview_skips_sms_flashes_once_and_preserves_admin_login(): void
    {
        $this->artisan('identity:provision-staff', ['mobile' => '09123456789', 'role' => 'admin'])->assertSuccessful();
        $this->post('/otp/request', ['mobile' => '09123456789', 'purpose' => 'login'])->assertRedirect('/login');
        $code = session('local_otp_code');
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $code);
        $this->assertSame([], $this->sms->messages);
        $this->assertTrue(Hash::check($code, OtpChallenge::sole()->code_hash));
        $this->assertArrayNotHasKey('preview_code', session('otp'));
        $this->get('/login')->assertOk()->assertSee($code)->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/login')->assertOk()->assertDontSee($code);
        $this->post('/otp/verify', ['code' => $code])->assertRedirect('/dashboard');
        $this->get('/admin')->assertOk();
        $this->assertNull(OtpChallenge::sole()->code_hash);
        $this->expectException(\App\Modules\Identity\Domain\OtpFailure::class);
        app(\App\Modules\Identity\Application\AuthService::class)->verify('09123456789', OtpChallenge::sole()->challenge_id, 'login', $code);
    }

    public function test_preview_keeps_cooldown_expiry_and_patient_permissions(): void
    {
        $this->post('/otp/request', ['mobile' => '09123456789', 'purpose' => 'login']);
        $code = session('local_otp_code');
        $this->postJson('/otp/request', ['mobile' => '09123456789', 'purpose' => 'login'])->assertStatus(429);
        $this->travel(120)->seconds();
        $this->postJson('/otp/verify', ['code' => $code])->assertUnprocessable();
        $this->assertGuest();
        $this->post('/otp/request', ['mobile' => '09123456789', 'purpose' => 'login', 'role' => 'admin']);
        $this->post('/otp/verify', ['code' => session('local_otp_code')])->assertRedirect('/dashboard');
        $this->get('/admin')->assertForbidden();
    }

    public function test_flag_off_uses_sms_and_hides_stale_preview(): void
    {
        config(['identity.local_otp_preview' => false]);
        $this->assertRealSms();
    }

    public function test_production_never_previews_even_with_flag_on(): void
    {
        $this->app->instance('env', 'production');
        config(['app.url' => 'https://localhost', 'session.secure' => true, 'session.encrypt' => true, 'session.http_only' => true, 'session.same_site' => 'lax']);
        \Illuminate\Support\Facades\URL::forceRootUrl('https://localhost');
        \Illuminate\Support\Facades\URL::forceScheme('https');
        $this->assertRealSms('https://localhost');
    }

    public function test_remote_peer_cannot_enable_preview_with_forwarded_headers(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->withHeaders(['X-Forwarded-For' => '127.0.0.1']);
        $this->assertRealSms();
    }

    public function test_public_origin_disables_preview_even_on_loopback(): void
    {
        config(['app.url' => 'http://doctor.example.com']);
        $this->assertRealSms();
    }

    private function assertRealSms(string $origin = ''): void
    {
        $this->post($origin.'/otp/request', ['mobile' => '09123456789', 'purpose' => 'login'])
            ->assertRedirect()->assertSessionMissing('local_otp_code');
        $code = $this->sms->lastCode();
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $code);
        $this->withSession(['local_otp_code' => 'PREVIEW_SECRET'])
            ->get($origin.'/login')->assertOk()->assertDontSee('PREVIEW_SECRET');
    }
}
