<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Identity\UuidGenerator;
use RuntimeException;

final class SequenceUuidGenerator implements UuidGenerator
{
    /** @param list<string> $identifiers */
    public function __construct(
        private array $identifiers,
    ) {}

    public function generate(): string
    {
        $identifier = array_shift($this->identifiers);
        if (!is_string($identifier)) {
            throw new RuntimeException('No test identifier remains.');
        }

        return $identifier;
    }
}
