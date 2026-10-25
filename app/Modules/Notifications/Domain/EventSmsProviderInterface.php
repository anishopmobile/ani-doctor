<?php

namespace App\Modules\Notifications\Domain;

interface EventSmsProviderInterface
{
    /** Returns the accepted provider message ID, never a claim of handset delivery. */
    public function sendTemplate(#[\SensitiveParameter] string $recipient, int $templateId, array $parameters): string;
    /** Returns only the provider's numeric delivery code; its meaning remains provider-specific. */
    public function queryDelivery(string $messageId): ?string;
}
