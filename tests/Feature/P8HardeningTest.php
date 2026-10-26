<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class P8HardeningTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->app['env'] = 'production';
        config(['app.url' => 'https://clinic.example.test', 'app.debug' => false, 'session.secure' => true, 'session.encrypt' => true, 'session.http_only' => true, 'session.same_site' => 'lax']);
    }

    protected function tearDown(): void
    {
        $this->app['env'] = 'testing';
        parent::tearDown();
    }

    public function test_https_origin_has_private_headers_and_hsts_only_when_explicitly_enabled(): void
    {
        $this->get('https://clinic.example.test/up')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')->assertHeaderMissing('Strict-Transport-Security');
        config(['operations.hsts' => true]);
        $this->get('https://clinic.example.test/up')->assertOk()->assertHeader('Strict-Transport-Security', 'max-age=31536000')->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_http_and_spoofed_forwarded_proto_never_reach_the_application(): void
    {
        $this->get('http://clinic.example.test/up', ['X-Forwarded-Proto' => 'https'])->assertStatus(426)->assertHeaderMissing('Location');
        $this->get('https://attacker.example.test/up')->assertStatus(421)->assertDontSee('attacker');
    }

    public function test_unsafe_production_configuration_fails_closed_without_debug_output(): void
    {
        foreach (['app.debug' => true, 'session.secure' => false, 'session.encrypt' => false, 'session.http_only' => false, 'session.same_site' => 'none'] as $key => $value) {
            $old = config($key);
            config([$key => $value]);
            $this->get('https://clinic.example.test/up')->assertStatus(503)->assertDontSee(config('app.key'));
            config([$key => $old]);
        }
    }

    public function test_only_explicit_proxy_ips_can_assert_https_and_forwarded_host_is_ignored(): void
    {
        config(['operations.trusted_proxies' => ['127.0.0.1']]);
        $this->get('http://clinic.example.test/up', ['X-Forwarded-Proto' => 'https'])->assertOk();
        $this->get('http://attacker.example.test/up', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'clinic.example.test'])->assertStatus(421);
        config(['operations.trusted_proxies' => ['*']]);
        $this->get('https://clinic.example.test/up')->assertStatus(503)->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_errors_keep_security_headers_and_do_not_log_exception_payloads(): void
    {
        Route::get('/p8-error', function (): void {
            throw new \RuntimeException('PRIVATE-MEDICAL-OTP-TOKEN-SENTINEL');
        });
        Log::spy();
        $this->get('https://clinic.example.test/p8-error')->assertStatus(500)->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff')->assertDontSee('PRIVATE-MEDICAL');
        Log::shouldHaveReceived('error')->once()->with('application.failure', ['type' => \RuntimeException::class]);
    }

    public function test_database_errors_on_login_are_redacted_too(): void
    {
        Route::get('/p8-login-error', function (): void {
            throw new \Illuminate\Database\QueryException('mysql', 'SELECT ?', ['PRIVATE-OTP-BINDING'], new \PDOException('PRIVATE-RAW-DATABASE-ERROR'));
        });
        Log::spy();
        $this->get('https://clinic.example.test/p8-login-error')->assertStatus(500)->assertDontSee('PRIVATE-OTP')->assertDontSee('SELECT');
        Log::shouldHaveReceived('error')->once()->with('booking.database_error', \Mockery::type('array'));
    }

    public function test_csrf_remains_required_in_production_and_callback_exception_is_narrow(): void
    {
        $this->post('https://clinic.example.test/otp/request', ['mobile' => '09120000000'])->assertStatus(419);
        $this->post('https://clinic.example.test/admin/discounts', [])->assertStatus(419);
        $this->postJson('https://clinic.example.test/payments/zarinpal/callback/'.\Illuminate\Support\Str::uuid(), [])->assertUnprocessable()->assertJsonValidationErrors(['Authority', 'Status']);
    }
}
