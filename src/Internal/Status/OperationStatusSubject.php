<?php

declare(strict_types=1);

namespace BlackOps\Internal\Status;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Identifier\OperationId;
use InvalidArgumentException;

final readonly class OperationStatusSubject
{
    private function __construct(
        public OperationId $operationId,
        public string $operationType,
        public ?ActorRef $originActor,
        public bool $expired,
    ) {
        if (!preg_match('/^[a-z0-9]+(?:\.[a-z0-9]+)*$/', $operationType)) {
            throw new InvalidArgumentException('Operation status subject requires a valid operation type.');
        }
    }

    public static function available(
        OperationId $operationId,
        string $operationType,
        ?ActorRef $originActor = null,
    ): self {
        return new self($operationId, $operationType, $originActor, false);
    }

    public static function expired(OperationId $operationId, string $operationType, ?ActorRef $originActor = null): self
    {
        return new self($operationId, $operationType, $originActor, true);
    }
}
