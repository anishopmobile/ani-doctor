<?php

namespace Tests\Feature;

use App\Modules\Identity\Application\StaffService;
use App\Modules\Identity\Domain\SmsProviderInterface;
use App\Modules\Identity\Infrastructure\OtpChallenge;
use App\Modules\Identity\Infrastructure\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeSmsProvider;
use Tests\TestCase;

final class IdentityLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_staff_logs_in_without_becoming_patient_and_session_rotates(): void
    {
        $this->seed();
        app(StaffService::class)->provision('09123456789', 'secretary');
        $sms = new FakeSmsProvider();
        $this->app->instance(SmsProviderInterface::class, $sms);
        $this->get('/login');
        $before = session()->getId();
        $this->post('/otp/request', ['mobile' => '09123456789', 'purpose' => 'login']);
        $this->post('/otp/verify', ['code' => $sms->lastCode()])->assertRedirect('/dashboard');
        $this->assertNotSame($before, session()->getId());
        $user = User::sole();
        $this->assertTrue($user->hasPermission('panel.secretary'));
        $this->assertFalse($user->hasPermission('panel.patient'));
    }

    public function test_pruning_erases_hash_without_removing_lock_row(): void
    {
        $sms = new FakeSmsProvider();
        $this->app->instance(SmsProviderInterface::class, $sms);
        app(\App\Modules\Identity\Application\AuthService::class)->requestCode('09123456789', 'guest');
        $this->travel(120)->seconds();
        $this->artisan('identity:prune-otp')->assertSuccessful();
        $this->assertNull(OtpChallenge::sole()->code_hash);
        $this->assertNotNull(OtpChallenge::sole()->sent_at);
    }

    public function test_existing_and_new_mobiles_receive_same_public_response(): void
    {
        $this->seed();
        User::create(['mobile' => '09123456789']);
        $this->app->instance(SmsProviderInterface::class, new FakeSmsProvider());
        foreach (['09123456789', '09123456788'] as $mobile) {
            $this->post('/otp/request', ['mobile' => $mobile, 'purpose' => 'login'])
                ->assertRedirect('/login')->assertSessionHas('status', 'کد تأیید ارسال شد.');
        }
    }
}
