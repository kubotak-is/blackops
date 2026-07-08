<?php

declare(strict_types=1);

namespace BlackOps\Http\Binding;

final readonly class BoundHttpValue
{
    private function __construct(
        public bool $found,
        public mixed $value,
    ) {}

    public static function found(mixed $value): self
    {
        return new self(true, $value);
    }

    public static function missing(): self
    {
        return new self(false, null);
    }
}
