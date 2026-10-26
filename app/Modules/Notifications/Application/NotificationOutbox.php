<?php

namespace App\Modules\Notifications\Application;

use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Identity\Domain\MobileNumber;
use App\Modules\Identity\Infrastructure\User;
use App\Modules\Scheduling\Application\JalaliDate;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class NotificationOutbox
{
    public const EVENTS = ['confirmed', 'rescheduled', 'cancelled', 'reminder', 'secretary'];
    /** Called inside the booking transaction: durable rows are visible to workers only on commit. */
    public function record(Appointment $appointment, string $event): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('OUTBOX_REQUIRES_TRANSACTION');
        }
        if (in_array($event, ['rescheduled', 'cancelled'], true)) {
            DB::table('message_outbox')->where('appointment_id', $appointment->id)->whereIn('state', ['queued', 'retry'])->whereIn('event', ['confirmed', 'rescheduled', 'reminder', 'secretary'])->update(['state' => 'superseded', 'updated_at' => now()]);
        }
        if (!config('notifications.enabled')) {
            return;
        }
        $this->enqueue($appointment, $event);
        if (in_array($event, ['confirmed', 'rescheduled'], true)) {
            $this->enqueue($appointment, 'reminder');
            $this->enqueue($appointment, 'secretary');
        }
    }
    private function enqueue(Appointment $appointment, string $event): void
    {
        $template = DB::table('sms_templates')->where('event', $event)->where('enabled', true)->first();
        if (!$template) {
            return;
        }
        $recipient = $appointment->notification_mobile;
        if ($event === 'secretary') {
            $user = User::find($template->secretary_id);
            $recipient = $user?->hasPermission('panel.secretary') ? $user->mobile : null;
        }
        // Historic guest hashes cannot be reversed; never infer a recipient from a patient's contact.
        if (!$recipient || !preg_match('/\A09[0-9]{9}\z/', MobileNumber::normalize($recipient))) {
            return;
        }
        $start = $appointment->start_at->setTimezone('Asia/Tehran');
        $available = $event === 'reminder' ? $appointment->start_at->subMinutes($template->reminder_minutes) : now();
        if ($event === 'reminder' && !$available->isFuture()) {
            return; // Late bookings receive confirmation, not an immediate duplicate reminder.
        }
        $parameters = ['DATE' => app(JalaliDate::class)->format($start), 'TIME' => $start->format('H:i'), 'REFERENCE' => $appointment->reference];
        DB::table('message_outbox')->insertOrIgnore(['event_key' => $appointment->reference.':'.$event.':'.$appointment->version, 'appointment_id' => $appointment->id, 'event' => $event, 'recipient' => Crypt::encryptString($recipient), 'parameters' => Crypt::encryptString(json_encode($parameters)), 'provider_template_id' => $template->provider_template_id, 'appointment_start' => $appointment->start_at, 'state' => 'queued', 'available_at' => $available, 'created_at' => now(), 'updated_at' => now()]);
    }
}
