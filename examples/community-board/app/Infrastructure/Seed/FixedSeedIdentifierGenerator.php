<?php

declare(strict_types=1);

namespace App\Infrastructure\Seed;

use App\Domain\Board\BoardIdGenerator;
use App\Identity\UuidGenerator;
use LogicException;

final class FixedSeedIdentifierGenerator implements BoardIdGenerator, UuidGenerator
{
    private bool $issued = false;

    public function __construct(
        private readonly string $identifier,
    ) {}

    public function generate(): string
    {
        if ($this->issued) {
            throw new LogicException('A seed identifier may only be issued once.');
        }

        $this->issued = true;

        return $this->identifier;
    }
}
