<?php

declare(strict_types=1);

namespace BlackOps\Core\Authorization;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Rejection\RejectionReason;

#[PublicApi]
final readonly class AuthorizationDecision
{
    private const int ALLOW = 0;
    private const int UNAUTHORIZED = 1;
    private const int FORBIDDEN = 2;

    private function __construct(
        private int $state,
        private ?string $code = null,
    ) {}

    public static function allow(): self
    {
        return new self(self::ALLOW);
    }

    public static function unauthorized(string $code): self
    {
        return new self(self::UNAUTHORIZED, RejectionReason::unauthorized($code)->code());
    }

    public static function forbid(string $code): self
    {
        return new self(self::FORBIDDEN, RejectionReason::forbidden($code)->code());
    }

    public function isAllowed(): bool
    {
        return $this->state === self::ALLOW;
    }

    public function isUnauthorized(): bool
    {
        return $this->state === self::UNAUTHORIZED;
    }

    public function isForbidden(): bool
    {
        return $this->state === self::FORBIDDEN;
    }

    public function code(): ?string
    {
        return $this->code;
    }
}
