<?php

declare(strict_types=1);

namespace BlackOps\Internal\Generator;

final readonly class AuthGenerationResult
{
    /**
     * @param list<string> $created
     * @param list<string> $updated
     */
    public function __construct(
        public array $created,
        public array $updated,
        public bool $current,
    ) {}
}
