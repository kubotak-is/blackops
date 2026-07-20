<?php

declare(strict_types=1);

namespace BlackOps\Outcome\Internal;

final readonly class StructuredOutcomeField
{
    /**
     * @param 'string'|'integer'|'float'|'boolean'|'dto'|'list' $kind
     */
    public function __construct(
        public string $name,
        public string $kind,
        public bool $nullable,
        public ?StructuredOutcomeShape $dto = null,
        public bool $sensitive = false,
    ) {}
}
