<?php

namespace App\Modules\Payments\Infrastructure;

use App\Modules\Booking\Domain\BookingFailure;
use App\Modules\Payments\Application\LocalPaymentMode;
use App\Modules\Payments\Domain\GatewayVerification;
use App\Modules\Payments\Domain\PaymentGatewayInterface;
use Illuminate\Support\Facades\Cache;

final class LocalSimulatorGateway implements PaymentGatewayInterface
{
    public function __construct(private readonly LocalPaymentMode $mode)
    {
    }

    private function guard(): void
    {
        if (!$this->mode->enabled()) {
            throw new BookingFailure('PAYMENT_NOT_FOUND', 404);
        }
    }

    private function record(string $authority): array
    {
        $this->guard();
        if (!preg_match('/^SLOCAL[a-f0-9]{30}$/D', $authority)) {
            throw new BookingFailure('PAYMENT_NOT_FOUND', 404);
        }
        $record = Cache::get('local-payment:'.$authority);
        if (!is_array($record)) {
            throw new BookingFailure('PAYMENT_NOT_FOUND', 404);
        }
        return $record;
    }

    public function createPayment(int $amountIrr, string $callback, string $merchant, bool $sandbox): string
    {
        $this->guard();
        if ($merchant !== 'local-simulator' || !$sandbox || $amountIrr <= 0
            || !str_starts_with($callback, $this->mode->settings()['callback_url'].'/')) {
            throw new BookingFailure('GATEWAY_UNCERTAIN', 503);
        }
        $authority = 'SLOCAL'.bin2hex(random_bytes(15));
        $owner = request()->session()->get('local_payment_owner');
        if (!is_string($owner)) {
            $owner = bin2hex(random_bytes(32));
            request()->session()->put('local_payment_owner', $owner);
        }
        Cache::put('local-payment:'.$authority, [
            'amount_irr' => $amountIrr, 'callback' => $callback, 'outcome' => null,
            'session_hash' => hash('sha256', $owner),
        ], now()->addDay());
        return $authority;
    }

    public function checkout(string $authority): array
    {
        $record = $this->record($authority);
        $owner = request()->session()->get('local_payment_owner');
        if (!is_string($owner) || !hash_equals($record['session_hash'], hash('sha256', $owner))) {
            throw new BookingFailure('PAYMENT_NOT_FOUND', 404);
        }
        return $record;
    }

    public function decide(string $authority, string $outcome): string
    {
        return Cache::lock('local-payment-decision:'.$authority, 10)->block(5, function () use ($authority, $outcome): string {
            $record = $this->checkout($authority);
            if (!in_array($outcome, ['success', 'failure'], true)) {
                throw new BookingFailure('VALIDATION_ERROR', 422);
            }
            // The first decision wins; repeated POSTs cannot change a failed payment to paid.
            $record['outcome'] ??= $outcome;
            Cache::put('local-payment:'.$authority, $record, now()->addDay());
            return $record['callback'].'?'.http_build_query([
                'Authority' => $authority, 'Status' => $record['outcome'] === 'success' ? 'OK' : 'NOK',
            ]);
        });
    }

    public function verifyPayment(int $amountIrr, string $authority, string $merchant, bool $sandbox): GatewayVerification
    {
        $record = $this->record($authority);
        $paid = $record['outcome'] === 'success' && $record['amount_irr'] === $amountIrr
            && $merchant === 'local-simulator' && $sandbox;
        return new GatewayVerification($paid, $paid ? 'LOCAL-'.$authority : null);
    }

    public function queryPayment(string $authority, string $merchant, bool $sandbox): string
    {
        $record = $this->record($authority);
        if ($merchant !== 'local-simulator' || !$sandbox) {
            return 'UNKNOWN';
        }
        return match ($record['outcome']) {
            'success' => 'PAID', 'failure' => 'FAILED', default => 'UNKNOWN',
        };
    }

    public function redirectUrl(string $authority, bool $sandbox): string
    {
        $this->checkout($authority);
        return rtrim((string) config('app.url'), '/').'/payments/local-simulator/'.$authority;
    }

    public function capabilities(): array
    {
        return ['currency' => 'IRR', 'query' => true, 'automatic_refund' => false, 'simulated' => true];
    }
}
