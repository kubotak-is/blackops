<?php

declare(strict_types=1);

namespace BlackOps\Core\Validation\Attribute;

use Attribute;
use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_PROPERTY)]
#[PublicApi]
final readonly class Choice
{
    /** @param array<array-key, mixed> $choices */
    public function __construct(
        public array $choices,
    ) {
        if (
            $choices === []
            || !array_is_list($choices)
            || !array_all(
                $choices,
                static fn(mixed $choice): bool => is_scalar($choice) && (!is_float($choice) || is_finite($choice)),
            )
        ) {
            throw new InvalidArgumentException('Choice requires a non-empty list of scalar values.');
        }

        /** @var list<bool|float|int|string> $choices */
        /** @var list<bool|float|int|string> $seen */
        $seen = [];
        foreach ($choices as $choice) {
            foreach ($seen as $previous) {
                if ($previous === $choice) {
                    throw new InvalidArgumentException('Choice values must not be repeated.');
                }
            }

            $seen[] = $choice;
        }
    }
}
