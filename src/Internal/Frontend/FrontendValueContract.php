<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend;

final readonly class FrontendValueContract
{
    /** @param list<FrontendValueFieldContract> $fields */
    public function __construct(
        public string $class,
        public array $fields,
    ) {}
}
