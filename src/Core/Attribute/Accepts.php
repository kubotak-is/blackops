<?php

declare(strict_types=1);

namespace BlackOps\Core\Attribute;

use BlackOps\Core\OperationValue;

#[PublicApi]
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Accepts
{
    /** @param class-string<OperationValue> $value */
    public function __construct(
        public string $value,
    ) {}
}
