<?php

namespace App\Modules\Payments\Application;

use App\Modules\Billing\Application\BookingFinance;
use App\Modules\Billing\Application\FinancialAudit;
use App\Modules\Booking\Application\BookingService;
use App\Modules\Booking\Application\FinancialBookingService;
use App\Modules\Booking\Domain\BookingFailure;
use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Identity\Domain\BookingActor;
use App\Modules\Payments\Infrastructure\CardTransfer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class CardTransferService
{
    public function __construct(private readonly FinancialBookingService $booking)
    {
    }

    public function configure(BookingActor $actor, array $data): void
    {
        if (!$actor->userId || !$actor->can('payments.settings')) {
            throw new BookingFailure('FORBIDDEN', 403);
        }
        DB::transaction(function () use ($actor, $data): void {
            // Serialize the initial settings insert as well as later updates.
            DB::table('payment_settings')->where('id', 1)->lockForUpdate()->first();
            $prior = DB::table('card_transfer_settings')->where('id', 1)->first();
            if ((int) ($prior->version ?? 0) !== (int) $data['expected_version']) {
                throw new BookingFailure('STALE_APPOINTMENT');
            }
            DB::table('card_transfer_settings')->updateOrInsert(['id' => 1], [
                'enabled' => $data['enabled'], 'card_number' => $data['card_number'], 'holder' => $data['holder'],
                'version' => ($prior->version ?? 0) + 1, 'updated_by' => $actor->userId, 'created_at' => $prior->created_at ?? now(), 'updated_at' => now(),
            ]);
            app(FinancialAudit::class)->record('card.settings', $actor->userId, 'card_settings', '1', ['version' => $prior->version ?? 0], ['version' => ($prior->version ?? 0) + 1], 'تنظیم کارت مقصد');
        }, 3);
    }

    public function begin(BookingActor $actor, Appointment $snapshot, int $version): CardTransfer
    {
        return DB::transaction(function () use ($actor, $snapshot, $version): CardTransfer {
            $settings = DB::table('card_transfer_settings')->where('id', 1)->sharedLock()->first();
            $appointment = $this->booking->lock($snapshot);
            app(BookingService::class)->authorize($actor, $appointment);
            $existing = CardTransfer::where('appointment_id', $appointment->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }
            if (!$settings?->enabled) {
                throw new BookingFailure('CARD_DISABLED', 503);
            }
            if ($appointment->version !== $version || !$this->booking->confirmable($appointment)) {
                throw new BookingFailure('HOLD_EXPIRED');
            }
            app(BookingFinance::class)->assertEditable($appointment);
            // Never restart or extend a hold. This channel always caps it at creation + 900 seconds.
            $deadline = $appointment->created_at->toImmutable()->addSeconds(900)->min($appointment->hold_expires_at);
            $appointment->forceFill(['hold_expires_at' => $deadline, 'version' => $appointment->version + 1])->save();
            return CardTransfer::create(['reference' => (string) Str::uuid(), 'appointment_id' => $appointment->id,
                'state' => 'awaiting_receipt', 'card_number' => $settings->card_number, 'holder' => $settings->holder,
                'amount_irr' => $appointment->price_irr, 'deadline' => $deadline]);
        }, 3);
    }

    public function submit(BookingActor $actor, Appointment $snapshot, UploadedFile $file): CardTransfer
    {
        Validator::make(['receipt' => $file], ['receipt' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120'])->validate();
        app(BookingService::class)->authorize($actor, $snapshot);
        $hash = hash_file('sha256', $file->getRealPath());
        $path = $file->store('card-receipts', 'local');
        if (!$path) {
            throw new BookingFailure('UPLOAD_FAILED', 503);
        }
        try {
            $result = DB::transaction(function () use ($actor, $snapshot, $hash, $path): CardTransfer {
                $appointment = $this->booking->lock($snapshot);
                app(BookingService::class)->authorize($actor, $appointment);
                $transfer = CardTransfer::where('appointment_id', $appointment->id)->lockForUpdate()->firstOrFail();
                if ($transfer->receipt_hash) {
                    if (!hash_equals($transfer->receipt_hash, $hash)) {
                        throw new BookingFailure('IDEMPOTENCY_CONFLICT');
                    }
                    return $transfer;
                }
                if ($transfer->state !== 'awaiting_receipt' || !$transfer->deadline->isFuture() || !$this->booking->confirmable($appointment)) {
                    throw new BookingFailure('HOLD_EXPIRED');
                }
                $transfer->forceFill(['state' => 'pending_review', 'receipt_path' => $path, 'receipt_hash' => $hash,
                    'duplicate_receipt' => CardTransfer::where('receipt_hash', $hash)->exists(), 'submitted_at' => now()])->save();
                $this->booking->confirm($appointment, 'card_pending', $appointment->price_irr, $actor->userId, 'رسید کارت‌به‌کارت دریافت شد؛ در انتظار تأیید', $actor->key);
                return $transfer;
            }, 3);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
        if ($result->receipt_path !== $path) {
            Storage::disk('local')->delete($path);
        }
        return $result;
    }

    public function review(BookingActor $actor, Appointment $snapshot, string $decision, string $reason): CardTransfer
    {
        if (!$actor->userId || !$actor->can('payments.card_review') || !in_array($decision, ['approve', 'reject'], true) || trim($reason) === '') {
            throw new BookingFailure('FORBIDDEN', 403);
        }
        return DB::transaction(function () use ($actor, $snapshot, $decision, $reason): CardTransfer {
            $appointment = $this->booking->lock($snapshot);
            $transfer = CardTransfer::where('appointment_id', $appointment->id)->lockForUpdate()->firstOrFail();
            if ($transfer->decision) {
                if ($transfer->decision !== $decision) {
                    throw new BookingFailure('INVALID_STATE');
                }
                return $transfer;
            }
            if ($transfer->state !== 'pending_review') {
                throw new BookingFailure('INVALID_STATE');
            }
            $before = $appointment->payment_status;
            if ($decision === 'approve') {
                DB::table('payments')->insert(['appointment_id' => $appointment->id, 'provider' => 'card_transfer',
                    'provider_reference' => $transfer->reference, 'amount_irr' => $transfer->amount_irr, 'credit_irr' => 0,
                    'allocated' => true, 'actor_id' => $actor->userId, 'reason' => 'تأیید دستی رسید', 'created_at' => now()]);
                $this->booking->financialStatus($appointment, 'paid', 0);
                $transfer->state = 'approved';
            } else {
                $needsManager = !$appointment->start_at->isFuture() || $appointment->attendance_status !== 'scheduled' || $appointment->arrived_at !== null;
                $transfer->state = $needsManager ? 'manager_review' : 'rejected';
                $this->booking->financialStatus($appointment, $needsManager ? 'reconciliation_required' : 'card_rejected', $appointment->price_irr);
                if (!$needsManager) {
                    app(\App\Modules\Billing\Application\DiscountService::class)->transition($appointment, 'released');
                    $appointment->status = 'cancelled';
                }
                app(\App\Modules\Bale\Application\BaleOutbox::class)->rejected($appointment, $needsManager);
            }
            $appointment->version++;
            $appointment->save();
            $transfer->forceFill(['decision' => $decision, 'reviewed_by' => $actor->userId, 'reviewed_at' => now(), 'reason' => $reason])->save();
            app(FinancialAudit::class)->record('card.'.$decision, $actor->userId, 'card_transfer', (string) $transfer->id, ['payment_status' => $before], ['state' => $transfer->state], $reason);
            return $transfer;
        }, 3);
    }
}
