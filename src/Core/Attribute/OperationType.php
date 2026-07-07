<?php

declare(strict_types=1);

namespace BlackOps\Core\Attribute;

use InvalidArgumentException;

#[PublicApi]
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class OperationType
{
    public function __construct(
        public string $id,
    ) {
        if (!preg_match('/^[a-z0-9]+(?:\.[a-z0-9]+)*$/', $id)) {
            throw new InvalidArgumentException('Operation type requires a valid dot-separated identifier.');
        }
    }
}
