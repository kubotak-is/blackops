<?php

declare(strict_types=1);

namespace BlackOps\Core\Attribute;

use BlackOps\Core\Outcome;

#[PublicApi]
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Returns
{
    /** @param class-string<Outcome> $outcome */
    public function __construct(
        public string $outcome,
    ) {}
}
