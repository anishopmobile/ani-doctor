<?php

namespace App\Modules\Payments\Domain;

interface PaymentGatewayInterface
{
    public function createPayment(int $amountIrr, string $callback, string $merchant, bool $sandbox): string;
    public function verifyPayment(int $amountIrr, string $authority, string $merchant, bool $sandbox): GatewayVerification;
    public function queryPayment(string $authority, string $merchant, bool $sandbox): string;
    public function redirectUrl(string $authority, bool $sandbox): string;
    public function capabilities(): array;
}
