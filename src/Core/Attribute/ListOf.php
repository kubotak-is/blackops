<?php

declare(strict_types=1);

namespace BlackOps\Core\Attribute;

use BlackOps\Core\OutcomeData;

#[PublicApi]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class ListOf
{
    /** @param class-string<OutcomeData> $type */
    public function __construct(
        public string $type,
    ) {}
}
