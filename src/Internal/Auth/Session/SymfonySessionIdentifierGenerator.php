<?php

declare(strict_types=1);

namespace BlackOps\Internal\Auth\Session;

use DateTimeImmutable;
use Symfony\Component\Uid\UuidV7;

final readonly class SymfonySessionIdentifierGenerator implements SessionIdentifierGenerator
{
    public function generate(DateTimeImmutable $time): string
    {
        return UuidV7::generate($time);
    }
}
