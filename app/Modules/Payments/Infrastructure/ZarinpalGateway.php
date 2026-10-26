<?php

namespace App\Modules\Payments\Infrastructure;

use App\Modules\Booking\Domain\BookingFailure;
use App\Modules\Payments\Domain\GatewayVerification;
use App\Modules\Payments\Domain\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;

final class ZarinpalGateway implements PaymentGatewayInterface
{
    private function call(string $method, array $data, bool $sandbox): array
    {
        try {
            $response = Http::acceptJson()->asJson()->connectTimeout(5)->timeout(15)
                ->withOptions(['allow_redirects' => false])
                ->post(($sandbox ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com').'/pg/v4/payment/'.$method.'.json', $data);
            if (!$response->successful() || !is_array($response->json())) {
                throw new \RuntimeException();
            }
            return $response->json();
        } catch (\Throwable) {
            // Never propagate HTTP bodies, request credentials or card information to logs.
            throw new BookingFailure('GATEWAY_UNCERTAIN', 503);
        }
    }
    public function createPayment(int $amountIrr, string $callback, string $merchant, bool $sandbox): string
    {
        $result = $this->call('request', ['merchant_id' => $merchant, 'amount' => $amountIrr, 'currency' => 'IRR', 'callback_url' => $callback, 'description' => 'پرداخت نوبت', 'metadata' => []], $sandbox);
        $authority = $result['data']['authority'] ?? '';
        if (($result['data']['code'] ?? null) !== 100 || !is_string($authority) || !preg_match('/^[AS][a-zA-Z0-9]{35}$/D', $authority)) {
            throw new BookingFailure('GATEWAY_UNCERTAIN', 503);
        }
        return $authority;
    }
    public function verifyPayment(int $amountIrr, string $authority, string $merchant, bool $sandbox): GatewayVerification
    {
        $result = $this->call('verify', ['merchant_id' => $merchant, 'amount' => $amountIrr, 'authority' => $authority], $sandbox);
        $reference = $result['data']['ref_id'] ?? null;
        $paid = in_array($result['data']['code'] ?? null, [100, 101], true) && (is_int($reference) || is_string($reference)) && preg_match('/^[1-9][0-9]{0,29}$/D', (string) $reference);
        return new GatewayVerification((bool) $paid, $paid ? (string) $reference : null);
    }
    public function queryPayment(string $authority, string $merchant, bool $sandbox): string
    {
        $result = $this->call('inquiry', ['merchant_id' => $merchant, 'authority' => $authority], $sandbox);
        return ($result['data']['code'] ?? null) === 100 ? ($result['data']['status'] ?? 'UNKNOWN') : 'UNKNOWN';
    }
    public function redirectUrl(string $authority, bool $sandbox): string
    {
        if (!preg_match('/^[AS][a-zA-Z0-9]{35}$/D', $authority)) {
            throw new BookingFailure('GATEWAY_UNCERTAIN', 503);
        }
        return ($sandbox ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com').'/pg/StartPay/'.$authority;
    }
    public function capabilities(): array
    {
        return ['currency' => 'IRR', 'query' => true, 'automatic_refund' => false];
    }
}
