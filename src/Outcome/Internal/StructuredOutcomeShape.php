<?php

declare(strict_types=1);

namespace BlackOps\Outcome\Internal;

final readonly class StructuredOutcomeShape
{
    /**
     * @param class-string $class
     * @param list<StructuredOutcomeField> $fields
     */
    public function __construct(
        public string $class,
        public array $fields,
    ) {}
}
