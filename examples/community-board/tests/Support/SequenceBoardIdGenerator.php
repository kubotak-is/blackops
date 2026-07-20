<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Board\BoardIdGenerator;
use LogicException;

final class SequenceBoardIdGenerator implements BoardIdGenerator
{
    /** @param list<string> $identifiers */
    public function __construct(
        private array $identifiers,
    ) {}

    public function generate(): string
    {
        return array_shift($this->identifiers) ?? throw new LogicException('No board identifier remains.');
    }
}
