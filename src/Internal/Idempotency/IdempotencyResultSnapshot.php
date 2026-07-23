<?php

declare(strict_types=1);

namespace BlackOps\Internal\Idempotency;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\OperationResult;

/** Keeps the original typed PHP result for an in-process duplicate replay. */
final readonly class IdempotencyResultSnapshot
{
    public function __construct(
        private ?OperationResult $result = null,
        private ?OperationId $internalFailureOperationId = null,
    ) {}

    public static function internalFailure(OperationId $operationId): self
    {
        return new self(null, $operationId);
    }

    public function result(): OperationResult
    {
        return $this->result ?? throw new \LogicException('Internal failure snapshot has no typed result.');
    }

    public function isInternalFailure(): bool
    {
        return $this->internalFailureOperationId !== null;
    }

    public function internalFailureOperationId(): OperationId
    {
        return $this->internalFailureOperationId ?? throw new \LogicException('Snapshot is not an internal failure.');
    }
}
