<?php

declare(strict_types=1);

namespace BlackOps\Core\Validation\Attribute;

use Attribute;
use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_PROPERTY)]
#[PublicApi]
final readonly class Range
{
    public function __construct(
        public int|float|null $min = null,
        public int|float|null $max = null,
    ) {
        if ($min === null && $max === null) {
            throw new InvalidArgumentException('Range requires a minimum or maximum value.');
        }

        if ($min !== null && !is_finite((float) $min) || $max !== null && !is_finite((float) $max)) {
            throw new InvalidArgumentException('Range bounds must be finite numbers.');
        }

        if ($min !== null && $max !== null && $min > $max) {
            throw new InvalidArgumentException('Range minimum must not exceed maximum.');
        }
    }
}
