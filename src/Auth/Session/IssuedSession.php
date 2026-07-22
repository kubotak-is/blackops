<?php

declare(strict_types=1);

namespace BlackOps\Auth\Session;

use BlackOps\Core\Attribute\PublicApi;
use DateTimeImmutable;
use SensitiveParameter;

#[PublicApi]
final readonly class IssuedSession
{
    public function __construct(
        #[SensitiveParameter]
        private RawSessionToken $token,
        private DateTimeImmutable $issuedAt,
        private DateTimeImmutable $expiresAt,
    ) {}

    public function token(): RawSessionToken
    {
        return $this->token;
    }

    public function issuedAt(): DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
