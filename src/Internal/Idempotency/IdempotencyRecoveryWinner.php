<?php

declare(strict_types=1);

namespace BlackOps\Internal\Idempotency;

use RuntimeException;

final class IdempotencyRecoveryWinner extends RuntimeException
{
    public function __construct(
        private readonly TerminalRecord $record,
    ) {
        parent::__construct('Another process completed idempotency recovery.');
    }

    public function record(): TerminalRecord
    {
        return $this->record;
    }
}
