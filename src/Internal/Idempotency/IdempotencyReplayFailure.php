<?php

declare(strict_types=1);

namespace BlackOps\Internal\Idempotency;

use BlackOps\Core\Identifier\OperationId;
use RuntimeException;

final class IdempotencyReplayFailure extends RuntimeException
{
    public function __construct(
        private readonly OperationId $operation,
    ) {
        parent::__construct('Idempotency replay reached a safe internal failure boundary.');
    }

    public function operationId(): OperationId
    {
        return $this->operation;
    }
}
