<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

final readonly class OperationConsoleInvocationResult
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public array $payload,
        public int $exitCode,
    ) {}
}
