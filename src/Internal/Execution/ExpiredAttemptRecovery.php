<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use DateTimeImmutable;

interface ExpiredAttemptRecovery
{
    public function recoverOne(DateTimeImmutable $expiredAt): bool;
}
