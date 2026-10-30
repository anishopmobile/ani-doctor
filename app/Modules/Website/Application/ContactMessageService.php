<?php

namespace App\Modules\Website\Application;

use App\Modules\Website\Infrastructure\WebsiteContactMessage;

final class ContactMessageService
{
    public function receive(array $data): void
    {
        WebsiteContactMessage::firstOrCreate(['submission_hash' => hash('sha256', $data['contact_submission'])], [
            'sender_name' => $data['contact_sender'], 'phone' => $data['contact_mobile'], 'message' => $data['contact_message'],
        ]);
    }

    public function markRead(WebsiteContactMessage $message): void
    {
        $message->forceFill(['read_at' => $message->read_at ?? now()])->save();
    }
}
