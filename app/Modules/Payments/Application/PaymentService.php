<?php

namespace App\Modules\Payments\Application;

use App\Modules\Booking\Application\BookingService;
use App\Modules\Booking\Application\FinancialBookingService;
use App\Modules\Booking\Domain\BookingFailure;
use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Identity\Domain\BookingActor;
use App\Modules\Payments\Domain\GatewayVerification;
use App\Modules\Payments\Domain\PaymentGatewayInterface;
use App\Modules\Payments\Infrastructure\PaymentAttempt;
use App\Modules\Payments\Infrastructure\LocalSimulatorGateway;
use App\Modules\Wallet\Application\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PaymentService
{
    public function __construct(private readonly FinancialBookingService $booking, private readonly WalletService $wallet, private readonly PaymentGatewayInterface $gateway, private readonly LocalPaymentMode $localMode, private readonly LocalSimulatorGateway $simulator)
    {
    }
    public function initiate(BookingActor $actor, Appointment $snapshot, array $data): PaymentAttempt
    {
        app(BookingService::class)->authorize($actor, $snapshot);
        $fingerprint = hash('sha256', json_encode([$snapshot->reference, (int) $data['credit_irr'], (int) $data['expected_version']]));
        [$attempt, $created] = DB::transaction(function () use ($actor, $snapshot, $data, $fingerprint): array {
            $appointment = $this->booking->lock($snapshot);
            app(BookingService::class)->authorize($actor, $appointment);
            $existing = PaymentAttempt::where('actor_hash', $actor->key)->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
            if ($existing) {
                if (!hash_equals($existing->payload_hash, $fingerprint)) {
                    throw new BookingFailure('IDEMPOTENCY_CONFLICT');
                }
                return [$existing, false];
            }
            if ($appointment->version !== (int) $data['expected_version']) {
                throw new BookingFailure('STALE_APPOINTMENT');
            }
            if (!$this->booking->confirmable($appointment)) {
                throw new BookingFailure('HOLD_EXPIRED');
            }
            if (PaymentAttempt::where('appointment_id', $appointment->id)->whereIn('state', ['requesting', 'redirected', 'uncertain'])->lockForUpdate()->exists()) {
                throw new BookingFailure('PAYMENT_IN_PROGRESS');
            }
            $credit = (int) $data['credit_irr'];
            if ($credit < 0 || $credit > $appointment->price_irr || ($credit > 0 && !hash_equals($actor->mobileHash, $appointment->booker_mobile_hash))) {
                throw new BookingFailure('FORBIDDEN', 403);
            }
            $amount = $appointment->price_irr - $credit;
            $local = $this->localMode->enabled();
            $settings = $local ? $this->localMode->settings() : config('payments.zarinpal');
            if ($amount > 0 && !$local && (!$settings['enabled'] || !Str::isUuid((string) $settings['merchant_id']) || !filter_var($settings['callback_url'], FILTER_VALIDATE_URL) || parse_url($settings['callback_url'], PHP_URL_SCHEME) !== 'https')) {
                throw new BookingFailure('GATEWAY_DISABLED', 503);
            }
            $this->wallet->reserve($appointment->booker_mobile_hash, $credit);
            $attempt = PaymentAttempt::create([
                'reference' => (string) Str::uuid(), 'appointment_id' => $appointment->id, 'actor_hash' => $actor->key,
                'idempotency_key' => $data['idempotency_key'], 'payload_hash' => $fingerprint, 'owner_hash' => $appointment->booker_mobile_hash,
                'amount_irr' => $amount, 'credit_irr' => $credit, 'credit_reserved' => $credit > 0,
                'merchant_secret' => $amount > 0 ? $settings['merchant_id'] : null, 'sandbox' => $settings['sandbox'],
                'hold_expires_at' => $appointment->hold_expires_at, 'state' => 'requesting',
                'provider' => $local ? 'local_simulator' : 'zarinpal',
            ]);
            return [$attempt, true];
        }, 3);
        if (!$created) {
            if ($attempt->amount_irr === 0 && $attempt->state === 'requesting') {
                return $this->settle($attempt, new GatewayVerification(true, 'wallet-'.$attempt->reference));
            }
            return $attempt;
        }
        if ($attempt->amount_irr === 0) {
            return $this->settle($attempt, new GatewayVerification(true, 'wallet-'.$attempt->reference));
        }
        // Neither request nor verify is retried by the HTTP client under a database lock.
        try {
            $base = $attempt->provider === 'local_simulator' ? $this->localMode->settings()['callback_url'] : config('payments.zarinpal.callback_url');
            $callback = rtrim($base, '/').'/'.$attempt->reference;
            $authority = $this->gatewayFor($attempt)->createPayment($attempt->amount_irr, $callback, $attempt->merchant_secret, $attempt->sandbox);
            DB::transaction(function () use ($attempt, $authority): void {
                $current = PaymentAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                $current->forceFill(['authority' => $authority, 'state' => 'redirected'])->save();
            }, 3);
        } catch (BookingFailure $error) {
            PaymentAttempt::whereKey($attempt->id)->where('state', 'requesting')->update(['state' => 'uncertain']);
            throw $error;
        }
        return $attempt->fresh();
    }
    public function callback(string $reference, string $authority, string $status): PaymentAttempt
    {
        $attempt = PaymentAttempt::where('reference', $reference)->firstOrFail();
        if (!$attempt->authority || !hash_equals($attempt->authority, $authority)) {
            throw new BookingFailure('PAYMENT_NOT_FOUND', 404);
        }
        return $this->verify($attempt, $status === 'OK');
    }
    public function verify(PaymentAttempt $attempt, bool $verifyFirst = true): PaymentAttempt
    {
        if (in_array($attempt->state, ['settled', 'credited'], true)) {
            return $attempt;
        }
        if (!$attempt->authority) {
            throw new BookingFailure('GATEWAY_UNCERTAIN', 503);
        }
        try {
            $gateway = $this->gatewayFor($attempt);
            $verification = $verifyFirst ? $gateway->verifyPayment($attempt->amount_irr, $attempt->authority, $attempt->merchant_secret, $attempt->sandbox) : new GatewayVerification(false);
            if ($verification->paid) {
                return $this->settle($attempt, $verification);
            }
            $status = $gateway->queryPayment($attempt->authority, $attempt->merchant_secret, $attempt->sandbox);
            if (in_array($status, ['PAID', 'VERIFIED'], true)) {
                // Inquiry is never proof of payment: verify must yield a valid reference.
                $verification = $gateway->verifyPayment($attempt->amount_irr, $attempt->authority, $attempt->merchant_secret, $attempt->sandbox);
                if ($verification->paid) {
                    return $this->settle($attempt, $verification);
                }
            }
            if (in_array($status, ['FAILED', 'REVERSED'], true)) {
                return $this->fail($attempt);
            }
        } catch (BookingFailure) {
            // Preserve reservations until a definitive response or server-side expiry.
        }
        PaymentAttempt::whereKey($attempt->id)->whereNotIn('state', ['settled', 'credited', 'failed'])->update(['state' => 'uncertain']);
        return $attempt->fresh();
    }
    public function settle(PaymentAttempt $snapshot, GatewayVerification $verification): PaymentAttempt
    {
        if ($snapshot->provider === 'local_simulator' && !$this->localMode->enabled()) {
            throw new BookingFailure('PAYMENT_NOT_FOUND', 404);
        }
        if (!$verification->paid || !$verification->reference) {
            throw new BookingFailure('GATEWAY_UNCERTAIN', 503);
        }
        $appointmentSnapshot = Appointment::findOrFail($snapshot->appointment_id);
        return DB::transaction(function () use ($snapshot, $verification, $appointmentSnapshot): PaymentAttempt {
            $appointment = $this->booking->lock($appointmentSnapshot);
            $attempt = PaymentAttempt::whereKey($snapshot->id)->lockForUpdate()->firstOrFail();
            if (in_array($attempt->state, ['settled', 'credited'], true)) {
                return $attempt;
            }
            $provider = $attempt->amount_irr > 0 ? $attempt->provider : 'wallet';
            $duplicate = DB::table('payments')->where('provider', $provider)->where('provider_reference', $verification->reference)->lockForUpdate()->first();
            if ($duplicate) {
                $attempt->state = 'uncertain';
                $attempt->save();
                DB::table('payment_reconciliations')->insertOrIgnore(['payment_attempt_id' => $attempt->id, 'reason' => 'reference_collision', 'resolution' => 'requires_review', 'created_at' => now()]);
                return $attempt;
            }
            $allocated = $attempt->hold_expires_at->isFuture() && $this->booking->confirmable($appointment)
                && ($attempt->credit_irr === 0 || $attempt->credit_reserved)
                && $attempt->amount_irr + $attempt->credit_irr === $appointment->price_irr;
            if ($attempt->credit_reserved) {
                $this->wallet->release($attempt->owner_hash, $attempt->credit_irr);
                $attempt->credit_reserved = false;
            }
            if ($allocated && $attempt->credit_irr > 0) {
                $this->wallet->entry($attempt->owner_hash, -$attempt->credit_irr, 'consume:'.$attempt->id, 'booking_payment', $appointment->id);
            }
            DB::table('payments')->insert(['appointment_id' => $appointment->id, 'payment_attempt_id' => $attempt->id, 'provider' => $provider, 'provider_reference' => $verification->reference, 'amount_irr' => $attempt->amount_irr, 'credit_irr' => $allocated ? $attempt->credit_irr : 0, 'allocated' => $allocated, 'created_at' => now()]);
            if ($allocated) {
                $this->booking->confirm($appointment, 'paid', 0, null, $attempt->provider === 'local_simulator' ? 'تسویه آزمایشی لوکال؛ بدون دریافت وجه واقعی' : 'تسویه کامل تأییدشده');
            } else {
                if ($attempt->amount_irr > 0) {
                    $this->wallet->entry($attempt->owner_hash, $attempt->amount_irr, 'late:'.$attempt->id, 'unallocated_payment', $appointment->id);
                }
                DB::table('payment_reconciliations')->insertOrIgnore(['payment_attempt_id' => $attempt->id, 'reason' => 'hold_unavailable_or_already_settled', 'resolution' => 'credited', 'created_at' => now()]);
            }
            $attempt->state = $allocated ? 'settled' : 'credited';
            $attempt->save();
            return $attempt;
        }, 3);
    }
    private function fail(PaymentAttempt $snapshot): PaymentAttempt
    {
        $appointment = Appointment::findOrFail($snapshot->appointment_id);
        return DB::transaction(function () use ($appointment, $snapshot): PaymentAttempt {
            $this->booking->lock($appointment);
            $attempt = PaymentAttempt::whereKey($snapshot->id)->lockForUpdate()->firstOrFail();
            if (!in_array($attempt->state, ['settled', 'credited'], true)) {
                if ($attempt->credit_reserved) {
                    $this->wallet->release($attempt->owner_hash, $attempt->credit_irr);
                    $attempt->credit_reserved = false;
                }
                $attempt->state = 'failed';
                $attempt->save();
            }
            return $attempt;
        }, 3);
    }
    public function redirect(PaymentAttempt $attempt): ?string
    {
        return $attempt->state === 'redirected' && $attempt->hold_expires_at->isFuture() && $attempt->authority ? $this->gatewayFor($attempt)->redirectUrl($attempt->authority, $attempt->sandbox) : null;
    }
    private function gatewayFor(PaymentAttempt $attempt): PaymentGatewayInterface
    {
        // Persisted provider identity prevents a mode switch from verifying real attempts locally.
        return $attempt->provider === 'local_simulator' ? $this->simulator : $this->gateway;
    }
}
