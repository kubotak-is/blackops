<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend;

final readonly class FrontendOutcomeTypeContract
{
    /**
     * @param 'scalar'|'dto'|'list' $kind
     * @param 'string'|'integer'|'float'|'boolean'|null $scalar
     * @param list<FrontendOutcomeFieldContract> $fields
     */
    public function __construct(
        public string $kind,
        public bool $nullable,
        public ?string $scalar = null,
        public ?string $class = null,
        public array $fields = [],
    ) {}
}
