<?php

namespace App\Modules\Notifications\Application;

use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Notifications\Domain\EventSmsProviderInterface;
use App\Modules\Notifications\Domain\SmsFailure;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class MessageDispatcher
{
    public function __construct(private readonly EventSmsProviderInterface $provider)
    {
    }
    public function dispatch(int $id): void
    {
        if (!config('notifications.enabled')) {
            return;
        }
        // Appointment first, then outbox: same order as booking mutations, no network under locks.
        $snapshot = DB::table('message_outbox')->find($id);
        if (!$snapshot) {
            return;
        }
        $message = DB::transaction(function () use ($id, $snapshot) {
            $appointment = Appointment::whereKey($snapshot->appointment_id)->lockForUpdate()->first();
            $row = DB::table('message_outbox')->where('id', $id)->lockForUpdate()->first();
            if (!$row || !in_array($row->state, ['queued', 'retry'], true) || now()->lt($row->available_at)) {
                return null;
            }
            $template = DB::table('sms_templates')->where('event', $row->event)->first();
            $valid = $template?->enabled && ($row->event === 'cancelled' ? $appointment->status === 'cancelled' : $appointment->status === 'confirmed' && $appointment->start_at->isFuture() && $appointment->start_at->equalTo($row->appointment_start));
            if (!$valid) {
                $this->state($id, 'superseded');
                return null;
            }
            if ($row->event === 'secretary') {
                $recipient = \App\Modules\Identity\Infrastructure\User::find($template->secretary_id);
                if (!$recipient?->hasPermission('panel.secretary') || !hash_equals(Crypt::decryptString($row->recipient), $recipient->mobile)) {
                    $this->state($id, 'superseded');
                    return null;
                }
            }
            DB::table('message_outbox')->where('id', $id)->update(['state' => 'sending', 'attempts' => $row->attempts + 1, 'claimed_at' => now(), 'updated_at' => now()]);
            $this->log($id, 'sending');
            return $row;
        }, 3);
        if (!$message) {
            return;
        }
        try {
            $providerId = $this->provider->sendTemplate(Crypt::decryptString($message->recipient), $message->provider_template_id, json_decode(Crypt::decryptString($message->parameters), true));
            $this->state($id, 'accepted', ['provider_message_id' => $providerId]);
        } catch (SmsFailure $error) {
            $attempt = $message->attempts + 1;
            $state = !$error->retryable ? 'uncertain' : ($attempt >= config('notifications.max_attempts') ? 'failed' : 'retry');
            $this->state($id, $state, ['available_at' => now()->addMinutes(2 ** $attempt)]);
        } catch (\Throwable) {
            // Never log the exception/request payload or automatically repeat an ambiguous send.
            $this->state($id, 'uncertain');
        }
    }
    public function run(): void
    {
        // A crashed process may have sent: timeout is uncertain, never returned to the queue.
        DB::table('message_outbox')->where('state', 'sending')->where('claimed_at', '<=', now()->subMinutes(5))->update(['state' => 'uncertain', 'updated_at' => now()]);
        $ids = DB::table('message_outbox')->whereIn('state', ['queued', 'retry'])->where('available_at', '<=', now())->orderBy('id')->limit(100)->pluck('id');
        foreach ($ids as $id) {
            $this->dispatch($id);
        }
    }
    public function poll(): void
    {
        if (!config('notifications.enabled')) {
            return;
        }
        $rows = DB::table('message_outbox')->where('state', 'accepted')->where('created_at', '>', now()->subDays(2))->where(fn ($q) => $q->whereNull('last_polled_at')->orWhere('last_polled_at', '<=', now()->subMinutes(15)))->limit(100)->get();
        foreach ($rows as $row) {
            $code = $this->provider->queryDelivery($row->provider_message_id);
            DB::transaction(function () use ($row, $code): void {
                DB::table('message_outbox')->where('id', $row->id)->update(['delivery_code' => $code, 'last_polled_at' => now(), 'updated_at' => now()]);
                $this->log($row->id, $code === null ? 'delivery_unknown' : 'delivery_reported');
            });
        }
    }
    private function state(int $id, string $state, array $extra = []): void
    {
        DB::transaction(function () use ($id, $state, $extra): void {
            DB::table('message_outbox')->where('id', $id)->update(['state' => $state, 'updated_at' => now(), ...$extra]);
            $this->log($id, $state);
        });
    }
    private function log(int $id, string $state): void
    {
        DB::table('sms_delivery_logs')->insert(['message_id' => $id, 'state' => $state, 'created_at' => now()]);
    }
}
