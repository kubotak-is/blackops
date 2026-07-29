<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduling;

use InvalidArgumentException;

final readonly class CronField
{
    /** @param list<int> $values */
    public function __construct(
        public array $values,
        public bool $wildcard,
    ) {
        if ($values === []) {
            throw new InvalidArgumentException('Cron field is invalid.');
        }
    }

    public function matches(int $value): bool
    {
        return in_array($value, $this->values, true);
    }
}
