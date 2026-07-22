<?php

declare(strict_types=1);

namespace BlackOps\Internal\Auth\Session;

use DateTimeImmutable;

final readonly class NewSessionRecord
{
    public function __construct(
        public string $id,
        public string $identityId,
        public string $tokenHash,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
    ) {}
}
