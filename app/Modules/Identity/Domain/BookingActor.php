<?php

namespace App\Modules\Identity\Domain;

final readonly class BookingActor
{
    public string $mobileHash;
    public string $key;

    public function __construct(public ?int $userId, public string $mobile, public array $permissions, string $secret)
    {
        $this->mobileHash = hash_hmac('sha256', $mobile, $secret);
        $this->key = hash_hmac('sha256', $userId ? 'user:'.$userId : 'guest:'.$mobile, $secret);
    }

    public function staff(): bool
    {
        return in_array('appointments.manage', $this->permissions, true);
    }

    public function owns(?int $ownerId, string $mobileHash): bool
    {
        return $this->staff() || ($this->userId !== null && $ownerId === $this->userId) || ($ownerId === null && hash_equals($this->mobileHash, $mobileHash));
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}
