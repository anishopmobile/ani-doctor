<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WebSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rtl_buildless_and_private(): void
    {
        $this->get('/login')->assertOk()->assertSee('lang="fa" dir="rtl"', false)
            ->assertSee('name="_token"', false)->assertDontSee('@vite')
            ->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertTrue(config('session.secure'));
        $this->assertTrue(config('session.http_only'));
        $this->assertTrue(config('session.encrypt'));
        $this->assertSame('UTC', config('app.timezone'));
    }

    public function test_csrf_is_enforced_when_test_bypass_is_disabled(): void
    {
        // Laravel normally bypasses CSRF in tests; change only the environment flag.
        $this->app->instance('env', 'local');
        $this->post('/otp/request', ['mobile' => '09123456789', 'purpose' => 'login'])->assertStatus(419);
        $this->post('/otp/verify', ['code' => '123456'])->assertStatus(419);
        $this->post('/logout')->assertStatus(419);
    }

    public function test_only_http_only_session_cookie_is_needed_and_redirects_have_no_body(): void
    {
        $response = $this->get('/login')->assertOk();
        $cookies = collect($response->headers->getCookies());
        $session = $cookies->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertNotNull($session);
        $this->assertTrue($session->isHttpOnly());
        $this->assertFalse($cookies->contains(fn ($cookie) => $cookie->getName() === 'XSRF-TOKEN'));
        $redirect = $this->get('/appointments')->assertRedirect('/login');
        $this->assertSame('', $redirect->getContent());
    }
}
