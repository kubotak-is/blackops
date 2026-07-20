<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend;

final readonly class FrontendOutcomeFieldContract
{
    public function __construct(
        public string $name,
        public FrontendOutcomeTypeContract $type,
    ) {}
}
