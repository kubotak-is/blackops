<?php

declare(strict_types=1);

namespace BlackOps\Internal\Auth\Session;

use DateTimeImmutable;

interface SessionStore
{
    public function insert(NewSessionRecord $session): void;

    public function authenticate(string $tokenHash, DateTimeImmutable $now, DateTimeImmutable $touchThreshold): ?string;

    public function rotate(
        string $tokenHash,
        string $successorId,
        string $successorTokenHash,
        DateTimeImmutable $issuedAt,
        DateTimeImmutable $expiresAt,
    ): ?string;

    public function revoke(string $tokenHash, DateTimeImmutable $revokedAt): void;

    public function cleanup(DateTimeImmutable $retentionCutoff, DateTimeImmutable $now): int;
}
