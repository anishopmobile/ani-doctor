<?php

namespace App\Modules\Bale\Application;

use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Bale\Infrastructure\BaleClient;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class BaleOutbox
{
    public function rejected(Appointment $appointment, bool $manager): void
    {
        $link = DB::table('bale_links')->where('user_id', $appointment->booker_user_id)->where('active', true)->first();
        if (!$link) {
            return;
        }
        $this->enqueue('card-rejected:'.$appointment->id, $link->bale_id, $manager ? 'پرداخت شما رد شد و برای بررسی به مدیر ارجاع شد. با مطب تماس بگیرید.' : 'پرداخت شما رد شد و نوبت لغو شد. می‌توانید دوباره نوبت بگیرید.');
    }

    public function enqueue(string $key, string $id, string $text): void
    {
        DB::table('bale_outbox')->insertOrIgnore(['event_key' => $key, 'bale_id' => $id, 'message' => Crypt::encryptString($text), 'state' => 'queued', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function dispatch(): void
    {
        foreach (DB::table('bale_outbox')->where('state', 'queued')->orderBy('id')->limit(50)->get() as $row) {
            // Claim before network: unknown outcomes need operator reconciliation, never blind resend.
            if (!DB::table('bale_outbox')->where('id', $row->id)->where('state', 'queued')->update(['state' => 'sending', 'updated_at' => now()])) {
                continue;
            }
            try {
                if (DB::table('bale_links')->where('bale_id', $row->bale_id)->where('active', true)->exists()) {
                    app(BaleClient::class)->call('sendMessage', ['chat_id' => $row->bale_id, 'text' => Crypt::decryptString($row->message)]);
                    $state = 'sent';
                } else {
                    $state = 'suppressed';
                }
            } catch (\Throwable) {
                $state = 'uncertain';
            }
            DB::table('bale_outbox')->where('id', $row->id)->update(['state' => $state, 'updated_at' => now()]);
        }
    }
}
