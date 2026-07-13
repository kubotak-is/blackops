<?php

declare(strict_types=1);

namespace BlackOps\Core\Validation\Attribute;

use Attribute;
use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_PROPERTY)]
#[PublicApi]
final readonly class Count
{
    public function __construct(
        public ?int $min = null,
        public ?int $max = null,
    ) {
        if ($min === null && $max === null) {
            throw new InvalidArgumentException('Count requires a minimum or maximum element count.');
        }

        if ($min !== null && $min < 0 || $max !== null && $max < 0) {
            throw new InvalidArgumentException('Count bounds must not be negative.');
        }

        if ($min !== null && $max !== null && $min > $max) {
            throw new InvalidArgumentException('Count minimum must not exceed maximum.');
        }
    }
}
