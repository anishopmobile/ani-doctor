<?php

namespace App\Modules\Billing\Application;

use App\Modules\Booking\Application\FinancialBookingService;
use App\Modules\Booking\Domain\BookingFailure;
use App\Modules\Booking\Domain\BookingFinanceInterface;
use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Payments\Infrastructure\PaymentAttempt;
use App\Modules\Wallet\Application\WalletService;
use Illuminate\Support\Facades\DB;

final class BookingFinance implements BookingFinanceInterface
{
    public function __construct(private readonly WalletService $wallet, private readonly FinancialBookingService $booking)
    {
    }
    public function assertEditable(Appointment $appointment): void
    {
        if (PaymentAttempt::where('appointment_id', $appointment->id)->whereIn('state', ['requesting', 'redirected', 'uncertain'])->lockForUpdate()->exists()) {
            throw new BookingFailure('PAYMENT_IN_PROGRESS');
        }
    }
    public function expire(Appointment $appointment): void
    {
        $attempts = PaymentAttempt::where('appointment_id', $appointment->id)->where('credit_reserved', true)->orderBy('id')->lockForUpdate()->get();
        foreach ($attempts as $attempt) {
            $this->wallet->release($attempt->owner_hash, $attempt->credit_irr);
            $attempt->credit_reserved = false;
            $attempt->save();
        }
    }
    public function cancel(Appointment $appointment): void
    {
        $this->expire($appointment);
        $paid = (int) DB::table('payments')->where('appointment_id', $appointment->id)->where('allocated', true)->selectRaw('COALESCE(SUM(amount_irr + credit_irr), 0) AS total')->first()->total;
        if ($appointment->payment_status !== 'unpaid' && $paid === 0 && $appointment->price_irr > 0) {
            // Do not mint credit for a legacy/manual database flag without a receipt.
            throw new BookingFailure('FINANCIAL_SETTLEMENT_REQUIRED');
        }
        if ($paid > 0) {
            $this->wallet->entry($appointment->booker_mobile_hash, $paid, 'cancel:'.$appointment->id, 'cancellation', $appointment->id);
        }
        $this->booking->financialStatus($appointment, $paid > 0 ? 'credited' : 'unpaid', 0);
    }
}
