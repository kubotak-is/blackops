<?php

declare(strict_types=1);

namespace BlackOps\Internal\Auth\Session;

use DateTimeImmutable;

interface SessionIdentifierGenerator
{
    public function generate(DateTimeImmutable $time): string;
}
