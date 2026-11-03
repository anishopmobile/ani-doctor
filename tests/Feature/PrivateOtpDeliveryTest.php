<?php
namespace Tests\Feature;
use App\Modules\Identity\Application\AuthService;
use App\Modules\Identity\Application\PrivateOtpDelivery;
use App\Modules\Identity\Domain\SmsProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeSmsProvider;
use Tests\TestCase;

final class PrivateOtpDeliveryTest extends TestCase
{
    use RefreshDatabase;
    private string $directory;
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->directory = sys_get_temp_dir().'/private-otp-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        config(['app.url' => 'https://clinic.example', 'identity.private_otp.enabled' => true,
            'identity.private_otp.mobile' => '09123456789', 'identity.private_otp.until' => gmdate('c', time()+3600),
            'identity.private_otp.directory' => $this->directory]);
        $this->app->instance(SmsProviderInterface::class, new FakeSmsProvider());
    }
    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) { unlink($file); }
        rmdir($this->directory);
        parent::tearDown();
    }
    public function test_private_delivery_exports_without_response_disclosure_and_consumes_once(): void
    {
        $service = app(AuthService::class);
        $result = $service->requestCode('09123456789', 'login');
        $this->assertArrayNotHasKey('preview_code', $result);
        $this->assertSame([], app(SmsProviderInterface::class)->messages);
        $this->artisan('identity:export-private-otp')->assertSuccessful();
        preg_match('/Code: ([0-9]{6})/', file_get_contents($this->directory.'/login-code.txt'), $match);
        $this->assertStringNotContainsString($match[1], file_get_contents($this->directory.'/pending.json'));
        $this->assertNotNull($service->verify('09123456789', $result['id'], 'login', $match[1]));
        $this->artisan('identity:export-private-otp')->assertSuccessful();
        $this->assertFileDoesNotExist($this->directory.'/login-code.txt');
        $this->assertFileDoesNotExist($this->directory.'/pending.json');
    }
    public function test_other_numbers_and_disabled_or_expired_settings_use_sms(): void
    {
        $delivery = app(PrivateOtpDelivery::class);
        $this->assertFalse($delivery->enabled('09123456788'));
        config(['identity.private_otp.until' => gmdate('c', time()-1)]);
        $this->assertFalse($delivery->enabled('09123456789'));
        app(AuthService::class)->requestCode('09123456789', 'login');
        $this->assertCount(1, app(SmsProviderInterface::class)->messages);
    }
    public function test_public_directory_fails_closed_without_sms_or_pending_secret(): void
    {
        config(['identity.private_otp.directory' => public_path()]);
        try {
            app(AuthService::class)->requestCode('09123456789', 'login');
            $this->fail('Unsafe path accepted');
        } catch (\App\Modules\Identity\Domain\OtpFailure $e) {
            $this->assertDatabaseHas('otp_challenges', ['mobile' => '09123456789', 'status' => 'failed', 'code_hash' => null]);
        }
    }
}
