<?php

declare(strict_types=1);

namespace App\Identity;

final readonly class AuthenticatedSession
{
    public function __construct(
        public User $user,
        public string $rawToken,
    ) {}
}
