<?php

namespace Tests\Support;

use App\Modules\Booking\Domain\BookingFailure;
use App\Modules\Payments\Domain\GatewayVerification;
use App\Modules\Payments\Domain\PaymentGatewayInterface;
use Illuminate\Support\Facades\DB;

final class FakePaymentGateway implements PaymentGatewayInterface
{
    public array $requests = [];
    public array $verifications = [];
    public bool $paid = true;
    public bool $requestTimeout = false;
    public bool $verifyTimeout = false;
    public string $queryStatus = 'FAILED';
    public ?string $forcedReference = null;
    public function createPayment(int $amountIrr, string $callback, string $merchant, bool $sandbox): string
    {
        $this->requests[] = [$amountIrr, $callback, $merchant, $sandbox, DB::transactionLevel()];
        if ($this->requestTimeout) {
            throw new BookingFailure('GATEWAY_UNCERTAIN', 503);
        }
        return 'S'.substr(hash('sha256', $callback), 0, 35);
    }
    public function verifyPayment(int $amountIrr, string $authority, string $merchant, bool $sandbox): GatewayVerification
    {
        $this->verifications[] = [$amountIrr, $authority, DB::transactionLevel()];
        if ($this->verifyTimeout) {
            throw new BookingFailure('GATEWAY_UNCERTAIN', 503);
        }
        return new GatewayVerification($this->paid, $this->paid ? ($this->forcedReference ?? (string) hexdec(substr(hash('sha256', $authority), 0, 12))) : null);
    }
    public function queryPayment(string $authority, string $merchant, bool $sandbox): string
    {
        return $this->queryStatus;
    }
    public function redirectUrl(string $authority, bool $sandbox): string
    {
        return 'https://sandbox.zarinpal.com/pg/StartPay/'.$authority;
    }
    public function capabilities(): array
    {
        return ['currency' => 'IRR', 'query' => true, 'automatic_refund' => false];
    }
}
