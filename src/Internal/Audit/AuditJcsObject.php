<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit;

final readonly class AuditJcsObject
{
    /**
     * Objects decoded from JSON use key/value pairs so a JSON member named
     * "0" is not coerced to an integer PHP array key.
     *
     * @param array<array-key,mixed> $members
     * @param list<bool> $keyValidity
     */
    public function __construct(
        public array $members,
        public array $keyValidity = [],
    ) {}
}
