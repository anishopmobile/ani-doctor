<?php

namespace Tests\Feature;

use App\Modules\Identity\Infrastructure\SmsIrProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SmsIrProviderTest extends TestCase
{
    public function test_adapter_uses_fixed_endpoint_and_template_contract(): void
    {
        config(['identity.sms_ir.api_key' => 'test-only-key', 'identity.sms_ir.otp_template_id' => '123']);
        Http::preventStrayRequests();
        Http::fake(['api.sms.ir/v1/send/verify' => Http::response(['status' => 1], 200)]);
        app(SmsIrProvider::class)->sendOtp('09123456789', '123456');
        Http::assertSent(fn ($request) => $request->url() === 'https://api.sms.ir/v1/send/verify'
            && $request->hasHeader('X-API-KEY', 'test-only-key') && $request['templateId'] === 123
            && $request['parameters'] === [['name' => 'CODE', 'value' => '123456']]);
    }

    public function test_provider_error_body_is_not_exposed(): void
    {
        config(['identity.sms_ir.api_key' => 'test-only-key', 'identity.sms_ir.otp_template_id' => '123']);
        Http::fake(['*' => Http::response(['status' => 0, 'message' => 'secret'], 200)]);
        $this->expectExceptionMessage('SMS_DELIVERY_FAILED');
        app(SmsIrProvider::class)->sendOtp('09123456789', '123456');
    }

    public function test_missing_credentials_fail_without_network(): void
    {
        Http::fake();
        try {
            app(SmsIrProvider::class)->sendOtp('09123456789', '123456');
            $this->fail('Expected configuration failure');
        } catch (\RuntimeException $error) {
            $this->assertSame('SMS_NOT_CONFIGURED', $error->getMessage());
        }
        Http::assertNothingSent();
    }
}
