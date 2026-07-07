<?php

declare(strict_types=1);

namespace BlackOps\Core\Attribute;

use BlackOps\Core\OperationHandler;

#[PublicApi]
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class HandledBy
{
    /** @param class-string<OperationHandler> $handler */
    public function __construct(
        public string $handler,
    ) {}
}
