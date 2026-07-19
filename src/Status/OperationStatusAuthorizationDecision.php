<?php

declare(strict_types=1);

namespace BlackOps\Status;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
final readonly class OperationStatusAuthorizationDecision
{
    private function __construct(
        private bool $allowed,
    ) {}

    public static function allow(): self
    {
        return new self(true);
    }

    public static function deny(): self
    {
        return new self(false);
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }
}
