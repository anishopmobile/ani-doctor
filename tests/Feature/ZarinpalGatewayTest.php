<?php

namespace Tests\Feature;

use App\Modules\Booking\Domain\BookingFailure;
use App\Modules\Payments\Infrastructure\ZarinpalGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ZarinpalGatewayTest extends TestCase
{
    public function test_request_sends_explicit_integer_irr_and_no_patient_metadata_to_fixed_endpoint(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'S'.str_repeat('0', 35)]])]);
        $gateway = new ZarinpalGateway();
        $authority = $gateway->createPayment(1234567, 'https://clinic.example/callback/opaque', 'test-merchant', true);
        $this->assertSame('https://sandbox.zarinpal.com/pg/StartPay/'.$authority, $gateway->redirectUrl($authority, true));
        Http::assertSent(fn ($request) => $request['amount'] === 1234567 && $request['currency'] === 'IRR' && $request['merchant_id'] === 'test-merchant' && $request['metadata'] === [] && $request['description'] === 'پرداخت نوبت');
        $this->assertFalse($gateway->capabilities()['automatic_refund']);
    }
    public function test_verify_accepts_100_and_101_only_with_valid_provider_reference_and_original_amount(): void
    {
        Http::fakeSequence()->push(['data' => ['code' => 100, 'ref_id' => 123, 'card_pan' => 'SECRET-CARD']])->push(['data' => ['code' => 101, 'ref_id' => 123]])->push(['data' => ['code' => 100]])->push(['data' => ['code' => -51, 'ref_id' => 123]])->push(['data' => ['code' => 100, 'ref_id' => 'https://evil.example']]);
        $gateway = new ZarinpalGateway();
        $first = $gateway->verifyPayment(1234567, 'A'.str_repeat('0', 35), 'test-merchant', false);
        $second = $gateway->verifyPayment(1234567, 'A'.str_repeat('0', 35), 'test-merchant', false);
        $this->assertTrue($first->paid);
        $this->assertSame('123', $first->reference);
        $this->assertEquals($first, $second);
        $this->assertStringNotContainsString('SECRET-CARD', json_encode($first));
        foreach (range(1, 3) as $unused) {
            $this->assertFalse($gateway->verifyPayment(1234567, 'A'.str_repeat('0', 35), 'test-merchant', false)->paid);
        }
        Http::assertSent(fn ($request) => $request->url() === 'https://payment.zarinpal.com/pg/v4/payment/verify.json' && $request['amount'] === 1234567);
    }
    public function test_transport_errors_do_not_leak_merchant_or_raw_response(): void
    {
        Http::fake(['*' => Http::response('private credentials and card data', 503)]);
        try {
            (new ZarinpalGateway())->createPayment(1000000, 'https://clinic.example/callback', 'SECRET-MERCHANT', false);
            $this->fail('Expected sanitized uncertainty.');
        } catch (BookingFailure $error) {
            $this->assertSame('GATEWAY_UNCERTAIN', $error->errorCode);
            $this->assertStringNotContainsString('SECRET', (string) $error);
            $this->assertNull($error->getPrevious());
        }
    }
    public function test_inquiry_has_separate_fixed_endpoint_and_redirect_rejects_untrusted_url(): void
    {
        Http::fake(['https://payment.zarinpal.com/pg/v4/payment/inquiry.json' => Http::response(['data' => ['code' => 100, 'status' => 'PAID']])]);
        $gateway = new ZarinpalGateway();
        $this->assertSame('PAID', $gateway->queryPayment('A'.str_repeat('1', 35), 'test-merchant', false));
        $this->expectException(BookingFailure::class);
        $gateway->redirectUrl('https://evil.example', false);
    }
}
