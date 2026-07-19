<?php

declare(strict_types=1);

namespace App\Identity;

use Closure;
use DateTimeImmutable;

interface IdentityRepository
{
    public function transactional(Closure $operation): mixed;

    public function findByCanonicalEmail(string $emailCanonical): ?StoredUser;

    public function findById(string $id): ?User;

    public function findByActiveTokenHash(string $tokenHash, DateTimeImmutable $now): ?User;

    public function createUser(
        string $id,
        string $emailCanonical,
        string $emailDisplay,
        string $displayName,
        string $passwordHash,
        DateTimeImmutable $now,
    ): void;

    public function updatePasswordHash(string $userId, string $passwordHash, DateTimeImmutable $now): void;

    public function createSession(
        string $id,
        string $userId,
        string $tokenHash,
        DateTimeImmutable $issuedAt,
        DateTimeImmutable $expiresAt,
    ): void;

    public function revokeSession(string $tokenHash, DateTimeImmutable $revokedAt): void;
}
