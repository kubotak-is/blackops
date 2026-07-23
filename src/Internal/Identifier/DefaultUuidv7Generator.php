<?php

declare(strict_types=1);

namespace BlackOps\Internal\Identifier;

use BlackOps\Identifier\Uuidv7Generator;
use Symfony\Component\Uid\UuidV7;

final readonly class DefaultUuidv7Generator implements Uuidv7Generator
{
    public function generate(): string
    {
        return UuidV7::generate();
    }
}
