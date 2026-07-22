<?php

declare(strict_types=1);

namespace BlackOps\Internal\Auth\Session;

use DateTimeImmutable;

interface SessionClock
{
    public function now(): DateTimeImmutable;
}
