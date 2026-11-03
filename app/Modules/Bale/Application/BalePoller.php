<?php

namespace App\Modules\Bale\Application;

use App\Modules\Bale\Infrastructure\BaleClient;
use App\Modules\Booking\Application\AppointmentAccess;
use App\Modules\Booking\Domain\BookingFailure;
use App\Modules\Medical\Application\MedicalInbox;
use App\Modules\Payments\Application\CardTransferService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

final class BalePoller
{
    public function once(): void
    {
        $offset = (int) DB::table('bale_poll_state')->where('id', 1)->value('offset');
        $updates = app(BaleClient::class)->call('getUpdates', ['offset' => $offset, 'limit' => 20, 'timeout' => 0]);
        foreach ($updates as $update) {
            if (!is_int($update['update_id'] ?? null) || $update['update_id'] < $offset) {
                continue;
            }
            $message = $update['message'] ?? [];
            $id = (string) ($message['from']['id'] ?? '');
            if (($message['chat']['type'] ?? '') === 'private' && $id === (string) ($message['chat']['id'] ?? '') && preg_match('/\A[1-9][0-9]{0,18}\z/', $id)) {
                try {
                    $this->message($id, $message, $update['update_id']);
                } catch (BookingFailure $error) {
                    if ($error->status === 503) {
                        throw $error;
                    }
                    app(BaleOutbox::class)->enqueue('update:'.$update['update_id'], $id, 'درخواست انجام نشد. اتصال حساب، بیمار انتخاب‌شده و مهلت رزرو را در مینی‌اپ بررسی کنید.');
                } catch (\Illuminate\Validation\ValidationException|\Illuminate\Database\Eloquent\ModelNotFoundException $error) {
                    app(BaleOutbox::class)->enqueue('update:'.$update['update_id'], $id, 'فایل یا بیمار انتخاب‌شده معتبر نیست. از بخش امن مینی‌اپ استفاده کنید.');
                }
            }
            $offset = $update['update_id'] + 1;
            DB::table('bale_poll_state')->where('id', 1)->update(['offset' => $offset]);
        }
    }

    private function message(string $id, array $message, int $update): void
    {
        if (!RateLimiter::attempt('bale-bot:'.$id, 20, fn () => true, 60)) {
            return;
        }
        $caption = $message['caption'] ?? '';
        $photo = $message['photo'] ?? [];
        $fileId = $message['document']['file_id'] ?? ($photo ? end($photo)['file_id'] ?? null : null);
        if ($fileId && preg_match('/\A\/(receipt|document) ([a-zA-Z0-9-]+)\z/', $caption, $matches)) {
            $actor = app(BaleIdentity::class)->actor($id);
            $appointment = null;
            if ($matches[1] === 'receipt') {
                $appointment = app(AppointmentAccess::class)->query($actor)->where('reference', $matches[2])->firstOrFail();
            } else {
                if (!ctype_digit($matches[2]) || !DB::table('patient_user_links')->where('user_id', $actor->userId)->where('patient_id', $matches[2])->exists()) {
                    throw new BookingFailure('FORBIDDEN', 404);
                }
            }
            $file = app(BaleClient::class)->download($fileId);
            try {
                if ($appointment) {
                    app(CardTransferService::class)->submit($actor, $appointment, $file);
                } else {
                    app(MedicalInbox::class)->submit($actor, (int) $matches[2], $file, 'bale:'.$update);
                }
            } finally {
                @unlink($file->getRealPath());
            }
            app(BaleOutbox::class)->enqueue('update:'.$update, $id, $appointment ? 'نوبت قطعی شد؛ پرداخت در انتظار تأیید منشی است.' : 'مدرک خصوصی برای بررسی دریافت شد.');
            return;
        }
        // No medical content is forwarded to staff or echoed into chat.
        app(BaleClient::class)->call('sendMessage', ['chat_id' => $id, 'text' => 'برای ورود امن، دریافت نوبت، انتخاب بیمار و ارسال مدارک، مینی‌اپ آنی دکتر را باز کنید.',
            'reply_markup' => ['inline_keyboard' => [[['text' => 'دریافت نوبت جدید و مدارک', 'web_app' => ['url' => route('bale.index')]]]]]]);
    }
}
