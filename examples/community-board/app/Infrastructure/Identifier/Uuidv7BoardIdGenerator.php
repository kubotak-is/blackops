<?php

declare(strict_types=1);

namespace App\Infrastructure\Identifier;

use App\Domain\Board\BoardIdGenerator;
use BlackOps\Identifier\Uuidv7Generator;

final readonly class Uuidv7BoardIdGenerator implements BoardIdGenerator
{
    public function __construct(
        private Uuidv7Generator $uuids,
    ) {}

    public function generate(): string
    {
        return $this->uuids->generate();
    }
}
