<?php

namespace App\Modules\Payments\Domain;

final readonly class GatewayVerification
{
    public function __construct(public bool $paid, public ?string $reference = null)
    {
    }
}
