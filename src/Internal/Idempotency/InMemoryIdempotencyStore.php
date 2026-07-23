<?php

declare(strict_types=1);

namespace BlackOps\Internal\Idempotency;

use BlackOps\Core\Execution\ExecutionStrategy;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Idempotency\IdempotencyKeyHash;
use DateTimeImmutable;

/**
 * Deterministic fixture for exercising the atomic store contract. It is not
 * registered by any production runtime composer.
 */
final class InMemoryIdempotencyStore implements IdempotencyStore
{
    /** @var array<string, ProcessingRecord|TerminalRecord> */
    private array $records = [];

    /** @mago-expect lint:excessive-parameter-list */
    public function claim(
        IdempotencyScopeHash $scope,
        IdempotencyKeyHash $key,
        OperationFingerprint $fingerprint,
        OperationId $operationId,
        ExecutionStrategy $strategy,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
    ): IdempotencyClaimResult {
        $scopeKey = $scope->version() . ':' . $scope->digest();
        $existing = $this->records[$scopeKey] ?? null;

        if ($existing !== null) {
            return new IdempotencyClaimResult(
                $existing->fingerprint()->equals($fingerprint)
                    ? IdempotencyClaimStatus::ExistingSameFingerprint
                    : IdempotencyClaimStatus::ExistingConflict,
                $existing,
            );
        }

        $record = new ProcessingRecord($scope, $key, $fingerprint, $operationId, $strategy, $createdAt, $expiresAt);
        $this->records[$scopeKey] = $record;

        return new IdempotencyClaimResult(IdempotencyClaimStatus::Claimed, $record);
    }

    public function terminalize(
        OperationId $operationId,
        TerminalRecord $record,
        IdempotencyRecordState $expectedState = IdempotencyRecordState::Processing,
    ): bool {
        $scopeKey = $record->scope()->version() . ':' . $record->scope()->digest();
        $existing = $this->records[$scopeKey] ?? null;

        if (
            !$existing instanceof ProcessingRecord
            || $expectedState !== IdempotencyRecordState::Processing
            || !$existing->scope()->equals($record->scope())
            || !$existing->operationId()->equals($operationId)
            || !$existing->fingerprint()->equals($record->fingerprint())
            || !$existing->key()->equals($record->key())
            || $existing->strategy()::class !== $record->strategy()::class
            || $existing->createdAt()->format('U.u') !== $record->createdAt()->format('U.u')
            || $existing->expiresAt()->format('U.u') !== $record->expiresAt()->format('U.u')
        ) {
            return false;
        }

        $this->records[$scopeKey] = $record;

        return true;
    }

    public function find(IdempotencyScopeHash $scope): ProcessingRecord|TerminalRecord|null
    {
        return $this->records[$scope->version() . ':' . $scope->digest()] ?? null;
    }
}
