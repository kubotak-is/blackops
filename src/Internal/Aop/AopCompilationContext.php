<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop;

final readonly class AopCompilationContext
{
    /** @param array<string, true> $connectionNames */
    public function __construct(
        public string $directory,
        public ?string $defaultConnection,
        public array $connectionNames,
    ) {}
}
