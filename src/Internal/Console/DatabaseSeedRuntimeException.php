<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use RuntimeException;

final class DatabaseSeedRuntimeException extends RuntimeException
{
    private function __construct(
        public readonly bool $artifactFailure,
    ) {
        parent::__construct('Database seeding runtime is unavailable.');
    }

    public static function artifact(): self
    {
        return new self(true);
    }

    public static function resolution(): self
    {
        return new self(false);
    }
}
