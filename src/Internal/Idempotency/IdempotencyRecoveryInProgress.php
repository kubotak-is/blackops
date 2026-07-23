<?php

declare(strict_types=1);

namespace BlackOps\Internal\Idempotency;

use RuntimeException;

final class IdempotencyRecoveryInProgress extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Idempotency recovery remains in progress.');
    }
}
