<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Relay;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration;
use BlackOps\Internal\Audit\Delivery\AuditOutboxState;
use BlackOps\Internal\Audit\Outbox\AuditOutboxLookupResult;
use BlackOps\Internal\Audit\Outbox\AuditOutboxRecord;
use BlackOps\Internal\Audit\Outbox\AuditOutboxStoreException;
use BlackOps\Internal\Audit\Outbox\AuditOutboxStoreFailure;
use BlackOps\Internal\Audit\Protection\AuditProtectionException;
use BlackOps\Internal\Audit\Storage\AuditAppendResult;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;
use Doctrine\DBAL\Connection;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final readonly class AuditOutboxRelayRuntime
{
    private const int MAX_SAFE_INTEGER = 9_007_199_254_740_991;

    public function __construct(
        private Connection $connection,
        private AuditRelayPersistence $persistence,
        private AuditIngressCanonicalizer $canonicalizer,
        private AuditOutboxConfiguration $configuration,
    ) {}

    public function recoverOne(): AuditRelayOutcome
    {
        $this->assertConnectionState();
        try {
            $result = $this->persistence->recoverNextExpired();
        } catch (AuditOutboxStoreException $failure) {
            return $this->lookupFailure($failure);
        } catch (\Throwable) {
            throw new AuditRelayException(AuditRelayFailure::StorageUnavailable);
        }
        $this->assertConnectionState();

        return $this->recoveryOutcome($result);
    }

    public function relayOne(): AuditRelayOutcome
    {
        $this->assertConnectionState();
        try {
            $result = $this->persistence->claimNext();
        } catch (AuditOutboxStoreException $failure) {
            return $this->lookupFailure($failure);
        } catch (\Throwable) {
            throw new AuditRelayException(AuditRelayFailure::StorageUnavailable);
        }
        $this->assertConnectionState();
        $claim = $this->claimDetails($result);
        if ($claim === null) {
            return AuditRelayOutcome::Idle;
        }
        try {
            $logical = $this->canonicalizer->canonicalize($claim['record']);
        } catch (AuditProtectionException) {
            return $this->settleSenderFailure($claim, true);
        } catch (AuditValidationException) {
            return $this->settleSenderFailure($claim, false);
        } catch (\Throwable) {
            throw new AuditRelayException(AuditRelayFailure::StorageUnavailable);
        }

        $this->assertConnectionState();
        try {
            $append = $this->persistence->appendCanonical($logical);
        } catch (AuditStoreException $failure) {
            return $this->settleSenderFailure($claim, $this->isRetryable($failure));
        } catch (\Throwable) {
            throw new AuditRelayException(AuditRelayFailure::StorageUnavailable);
        }
        $this->assertConnectionState();

        try {
            $inserted = $this->committedAppendInserted($claim, $append);
        } catch (\Throwable) {
            return $this->settlePoison($claim);
        }

        return $this->settleRelay($claim, $inserted);
    }

    /** @return array{record:AuditOutboxRecord,recordId:string,tenant:?AuditTypedReference,partitionId:string,version:int,token:string,attempt:int,contentHash:string}|null */
    private function claimDetails(#[\SensitiveParameter] AuditOutboxLookupResult $result): ?array
    {
        try {
            if (!$result->isFound()) {
                return null;
            }
            $record = $result->record();
            if (!$record instanceof AuditOutboxRecord) {
                throw new \RuntimeException('Claimed record is unavailable.');
            }
            $value = $record->toArray();
            if (($value['state'] ?? null) !== AuditOutboxState::Leased->value) {
                throw new \RuntimeException('Claimed record state is unavailable.');
            }

            $recordId = $value['recordId'] ?? null;
            $partitionId = $value['partitionId'] ?? null;
            $attempt = $value['attempt'] ?? null;
            $version = $value['version'] ?? null;
            $token = $value['leaseFencingToken'] ?? null;
            $expiresAt = $value['leaseExpiresAt'] ?? null;
            $contentHash = $value['contentHash'] ?? null;
            if (
                !is_string($recordId)
                || !self::isUuidV7($recordId)
                || !is_string($partitionId)
                || !self::isPartition($partitionId)
                || !is_int($attempt)
                || $attempt < 1
                || $attempt > $this->configuration->maxAttempts
                || !is_int($version)
                || $version < 1
                || $version > self::MAX_SAFE_INTEGER
                || !is_string($token)
                || !self::isUuidV7($token)
                || !is_string($expiresAt)
                || $expiresAt === ''
                || !is_string($contentHash)
                || !self::isHash($contentHash)
                || ($value['availableAt'] ?? null) !== null
            ) {
                throw new \RuntimeException('Claimed record fence is unavailable.');
            }

            return [
                'record' => $record,
                'recordId' => $recordId,
                'tenant' => $this->tenant($value['tenantBinding'] ?? null),
                'partitionId' => $partitionId,
                'version' => $version,
                'token' => $token,
                'attempt' => $attempt,
                'contentHash' => $contentHash,
            ];
        } catch (\Throwable) {
            throw new AuditRelayException(AuditRelayFailure::StorageUnavailable);
        }
    }

    private function recoveryOutcome(#[\SensitiveParameter] AuditOutboxLookupResult $result): AuditRelayOutcome
    {
        try {
            if (!$result->isFound()) {
                return AuditRelayOutcome::Idle;
            }
            $record = $result->record();
            if (!$record instanceof AuditOutboxRecord) {
                throw new \RuntimeException('Recovered record is unavailable.');
            }
            return match ($record->stateSnapshot()->state()) {
                AuditOutboxState::Pending => AuditRelayOutcome::RecoveredPending,
                AuditOutboxState::Poisoned => AuditRelayOutcome::RecoveryPoisoned,
                default => throw new \RuntimeException('Recovered record state is unavailable.'),
            };
        } catch (\Throwable) {
            throw new AuditRelayException(AuditRelayFailure::StorageUnavailable);
        }
    }

    /** @param array{record:AuditOutboxRecord,recordId:string,tenant:?AuditTypedReference,partitionId:string,version:int,token:string,attempt:int,contentHash:string} $claim */
    private function committedAppendInserted(
        #[\SensitiveParameter]
        array $claim,
        #[\SensitiveParameter]
        AuditAppendResult $append,
    ): bool {
        $stored = $append->record();
        $value = $stored->toArray();
        if (
            ($value['recordId'] ?? null) !== $claim['recordId']
            || ($value['partitionId'] ?? null) !== $claim['partitionId']
            || ($value['tenantBinding'] ?? null) !== $claim['tenant']?->toArray()
            || ($value['contentHash'] ?? null) !== $claim['contentHash']
        ) {
            throw new \RuntimeException('Committed record binding is unavailable.');
        }

        return $append->inserted();
    }

    /**
     * @mago-expect lint:no-boolean-flag-parameter
     * @param array{record:AuditOutboxRecord,recordId:string,tenant:?AuditTypedReference,partitionId:string,version:int,token:string,attempt:int,contentHash:string} $claim
     */
    private function settleSenderFailure(#[\SensitiveParameter] array $claim, bool $retryable): AuditRelayOutcome
    {
        if ($retryable && $claim['attempt'] < $this->configuration->maxAttempts) {
            return $this->settleRetry($claim);
        }

        return $this->settlePoison($claim);
    }

    private function isRetryable(#[\SensitiveParameter] AuditStoreException $failure): bool
    {
        return match ($failure->failure()) {
            AuditStoreFailure::ResourceUnavailable, AuditStoreFailure::StorageUnavailable => true,
            AuditStoreFailure::IdempotencyConflict,
            AuditStoreFailure::SegmentConflict,
            AuditStoreFailure::IntegrityInvalid,
                => false,
        };
    }

    /** @param array{record:AuditOutboxRecord,recordId:string,tenant:?AuditTypedReference,partitionId:string,version:int,token:string,attempt:int,contentHash:string} $claim */
    private function settleRetry(#[\SensitiveParameter] array $claim): AuditRelayOutcome
    {
        $this->assertConnectionState();
        try {
            $settled = $this->persistence->retry(
                $claim['recordId'],
                $claim['tenant'],
                $claim['partitionId'],
                $claim['version'],
                $claim['token'],
            );
        } catch (AuditOutboxStoreException $failure) {
            return $this->settlementFailure($failure);
        } catch (\Throwable) {
            throw new AuditRelayException(AuditRelayFailure::StorageUnavailable);
        }
        $this->assertConnectionState();
        $this->assertSettlementState($settled, AuditOutboxState::Pending);

        return AuditRelayOutcome::Retried;
    }

    /** @param array{record:AuditOutboxRecord,recordId:string,tenant:?AuditTypedReference,partitionId:string,version:int,token:string,attempt:int,contentHash:string} $claim */
    private function settlePoison(#[\SensitiveParameter] array $claim): AuditRelayOutcome
    {
        $this->assertConnectionState();
        try {
            $settled = $this->persistence->poison(
                $claim['recordId'],
                $claim['tenant'],
                $claim['partitionId'],
                $claim['version'],
                $claim['token'],
            );
        } catch (AuditOutboxStoreException $failure) {
            return $this->settlementFailure($failure);
        } catch (\Throwable) {
            throw new AuditRelayException(AuditRelayFailure::StorageUnavailable);
        }
        $this->assertConnectionState();
        $this->assertSettlementState($settled, AuditOutboxState::Poisoned);

        return AuditRelayOutcome::Poisoned;
    }

    /**
     * @mago-expect lint:no-boolean-flag-parameter
     * @param array{record:AuditOutboxRecord,recordId:string,tenant:?AuditTypedReference,partitionId:string,version:int,token:string,attempt:int,contentHash:string} $claim
     */
    private function settleRelay(#[\SensitiveParameter] array $claim, bool $inserted): AuditRelayOutcome
    {
        $this->assertConnectionState();
        try {
            $settled = $this->persistence->relay(
                $claim['recordId'],
                $claim['tenant'],
                $claim['partitionId'],
                $claim['version'],
                $claim['token'],
            );
        } catch (AuditOutboxStoreException $failure) {
            return $this->settlementFailure($failure);
        } catch (\Throwable) {
            throw new AuditRelayException(AuditRelayFailure::StorageUnavailable);
        }
        $this->assertConnectionState();
        $this->assertSettlementState($settled, AuditOutboxState::Relayed);

        return $inserted ? AuditRelayOutcome::RelayedInserted : AuditRelayOutcome::RelayedExisting;
    }

    private function assertSettlementState(
        #[\SensitiveParameter]
        AuditOutboxRecord $record,
        AuditOutboxState $expected,
    ): void {
        try {
            if ($record->stateSnapshot()->state() !== $expected) {
                throw new \RuntimeException('Settlement state is unavailable.');
            }
        } catch (AuditRelayException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw new AuditRelayException(AuditRelayFailure::StorageUnavailable);
        }
    }

    private function assertConnectionState(): void
    {
        try {
            if (!$this->connection->isAutoCommit() || $this->connection->isTransactionActive()) {
                throw new AuditRelayException(AuditRelayFailure::TransactionInvalid);
            }
            if ($this->connection->getTransactionNestingLevel() !== 0) {
                throw new AuditRelayException(AuditRelayFailure::TransactionInvalid);
            }
        } catch (AuditRelayException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw new AuditRelayException(AuditRelayFailure::TransactionInvalid);
        }
    }

    private function lookupFailure(#[\SensitiveParameter] AuditOutboxStoreException $failure): AuditRelayOutcome
    {
        return match ($failure->failure()) {
            AuditOutboxStoreFailure::TransitionConflict => AuditRelayOutcome::Stale,
            AuditOutboxStoreFailure::ResourceUnavailable
                => throw new AuditRelayException(AuditRelayFailure::ResourceUnavailable),
            AuditOutboxStoreFailure::IntegrityInvalid,
            AuditOutboxStoreFailure::IdempotencyConflict,
            AuditOutboxStoreFailure::StorageUnavailable,
                => throw new AuditRelayException(AuditRelayFailure::StorageUnavailable),
        };
    }

    private function settlementFailure(#[\SensitiveParameter] AuditOutboxStoreException $failure): AuditRelayOutcome
    {
        return match ($failure->failure()) {
            AuditOutboxStoreFailure::TransitionConflict => AuditRelayOutcome::Stale,
            AuditOutboxStoreFailure::ResourceUnavailable
                => throw new AuditRelayException(AuditRelayFailure::ResourceUnavailable),
            AuditOutboxStoreFailure::IntegrityInvalid,
            AuditOutboxStoreFailure::IdempotencyConflict,
            AuditOutboxStoreFailure::StorageUnavailable,
                => throw new AuditRelayException(AuditRelayFailure::StorageUnavailable),
        };
    }

    private function tenant(#[\SensitiveParameter] mixed $value): ?AuditTypedReference
    {
        if ($value === null) {
            return null;
        }
        if (
            !is_array($value)
            || array_keys($value) !== ['type', 'id']
            || !is_string($value['type'])
            || !is_string($value['id'])
        ) {
            throw new \RuntimeException('Claimed tenant is unavailable.');
        }

        return new AuditTypedReference($value['type'], $value['id']);
    }

    private static function isUuidV7(#[\SensitiveParameter] string $value): bool
    {
        return (
            strlen($value) === 36
            && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $value) === 1
        );
    }

    private static function isPartition(#[\SensitiveParameter] string $value): bool
    {
        return preg_match('/\A(?:system:global|tenant:[a-f0-9]{64})\z/D', $value) === 1;
    }

    private static function isHash(#[\SensitiveParameter] string $value): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
    }
}
