<?php

declare(strict_types=1);

namespace BlackOps\Http\Authentication;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Rejection\RejectionReason;

#[PublicApi]
final readonly class AuthenticationResult
{
    private const int ANONYMOUS = 0;
    private const int AUTHENTICATED = 1;
    private const int INVALID = 2;

    private function __construct(
        private int $state,
        private ?ActorRef $actor = null,
        private ?string $code = null,
    ) {}

    public static function anonymous(): self
    {
        return new self(self::ANONYMOUS);
    }

    public static function authenticated(ActorRef $actor): self
    {
        return new self(self::AUTHENTICATED, actor: $actor);
    }

    public static function invalid(string $code): self
    {
        return new self(self::INVALID, code: RejectionReason::unauthorized($code)->code());
    }

    public function isAnonymous(): bool
    {
        return $this->state === self::ANONYMOUS;
    }

    public function isAuthenticated(): bool
    {
        return $this->state === self::AUTHENTICATED;
    }

    public function isInvalid(): bool
    {
        return $this->state === self::INVALID;
    }

    public function actor(): ?ActorRef
    {
        return $this->actor;
    }

    public function code(): ?string
    {
        return $this->code;
    }
}
