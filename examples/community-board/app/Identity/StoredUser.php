<?php

declare(strict_types=1);

namespace App\Identity;

final readonly class StoredUser
{
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
        public string $passwordHash,
    ) {}

    public function user(): User
    {
        return new User($this->id, $this->email, $this->displayName);
    }
}
