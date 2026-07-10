<?php

declare(strict_types=1);

namespace BlackOps\Core\Retention;

use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;

#[PublicApi]
final readonly class RetentionActorRef
{
    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new InvalidArgumentException('Retention actor reference must not be empty.');
        }

        return new self($normalized);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
