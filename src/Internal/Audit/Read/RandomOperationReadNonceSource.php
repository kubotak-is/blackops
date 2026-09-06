<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Read;

final readonly class RandomOperationReadNonceSource implements OperationReadNonceSource
{
    public function generate(): string
    {
        return random_bytes(16);
    }
}
