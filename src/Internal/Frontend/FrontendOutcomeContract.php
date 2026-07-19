<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend;

final readonly class FrontendOutcomeContract
{
    /** @param list<FrontendOutcomeFieldContract> $fields */
    public function __construct(
        public string $class,
        public string $mode,
        public array $fields,
    ) {}
}
