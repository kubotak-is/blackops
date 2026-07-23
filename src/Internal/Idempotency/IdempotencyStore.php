<?php

declare(strict_types=1);

namespace BlackOps\Internal\Idempotency;

use BlackOps\Core\Execution\ExecutionStrategy;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Idempotency\IdempotencyKeyHash;
use DateTimeImmutable;

interface IdempotencyStore
{
    /** @mago-expect lint:excessive-parameter-list */
    public function claim(
        IdempotencyScopeHash $scope,
        IdempotencyKeyHash $key,
        OperationFingerprint $fingerprint,
        OperationId $operationId,
        ExecutionStrategy $strategy,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
    ): IdempotencyClaimResult;

    public function terminalize(
        OperationId $operationId,
        TerminalRecord $record,
        IdempotencyRecordState $expectedState = IdempotencyRecordState::Processing,
    ): bool;

    public function find(IdempotencyScopeHash $scope): ProcessingRecord|TerminalRecord|null;

    public function attachResponse(OperationId $operationId, IdempotencyResponseSnapshot $snapshot): bool;

    public function response(OperationId $operationId): ?IdempotencyResponseSnapshot;
}
