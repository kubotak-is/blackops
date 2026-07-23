<?php

declare(strict_types=1);

namespace BlackOps\Internal\Idempotency;

use BlackOps\Core\Execution\ExecutionStrategy;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Idempotency\IdempotencyKeyHash;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class TerminalRecord
{
    public function __construct(
        private IdempotencyScopeHash $scope,
        private IdempotencyKeyHash $key,
        private OperationFingerprint $fingerprint,
        private OperationId $operationId,
        private ExecutionStrategy $strategy,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $expiresAt,
    ) {
        if ($expiresAt <= $createdAt) {
            throw new InvalidArgumentException('Idempotency record expiry must be later than creation.');
        }
    }

    public function state(): IdempotencyRecordState
    {
        return IdempotencyRecordState::Terminal;
    }

    public function scope(): IdempotencyScopeHash
    {
        return $this->scope;
    }

    public function key(): IdempotencyKeyHash
    {
        return $this->key;
    }

    public function fingerprint(): OperationFingerprint
    {
        return $this->fingerprint;
    }

    public function operationId(): OperationId
    {
        return $this->operationId;
    }

    public function strategy(): ExecutionStrategy
    {
        return $this->strategy;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
