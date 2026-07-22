<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use DateTimeImmutable;

final readonly class User
{
    public function __construct(
        public string $id,
        public string $email,
        public string $canonicalEmail,
        public string $displayName,
        public string $passwordHash,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
