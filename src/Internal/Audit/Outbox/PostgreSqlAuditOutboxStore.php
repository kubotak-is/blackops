<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Outbox;

use BlackOps\Core\Audit\AuditRecordDraft;
use BlackOps\Core\Audit\AuditRecordTime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Audit\AuditValidationFailure;
use BlackOps\Internal\Audit\Delivery\AuditOutboxConfiguration;
use BlackOps\Internal\Audit\Delivery\AuditOutboxStateSnapshot;
use BlackOps\Internal\Audit\Delivery\AuditOutboxTransition;
use BlackOps\Internal\Audit\Protection\AuditIngressEnvelope;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectedIngress;
use BlackOps\Internal\Audit\Protection\AuditProtectionException;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordFinalizer;
use BlackOps\Internal\Audit\Record\AuditRecordStamp;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;

/**
 * Persists durable audit ingress and exposes the fenced delivery operations.
 * The table contains immutable protected ingress and a separately mutable C1
 * state snapshot; all decoding and state changes cross this one boundary.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:halstead
 */
final class PostgreSqlAuditOutboxStore
{
    private const int MAX_SAFE_INTEGER = 9_007_199_254_740_991;

    private const string UUID_V7_PATTERN = '/\A[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D';

    private const string PARTITION_PATTERN = '/\A(?:system:global|tenant:[a-f0-9]{64})\z/D';

    /** @var list<string> */
    private const array RECORD_COLUMNS = [
        'schema_version',
        'kind',
        'record_id',
        'partition_id',
        'tenant_type',
        'tenant_id',
        'ingress_ordinal',
        'idempotency_key_hash',
        'content_hash',
        'state',
        'attempt',
        'available_at',
        'lease_fencing_token',
        'lease_expires_at',
        'protected_body',
        'version',
    ];

    /** @var list<string> */
    private const array CANDIDATE_COLUMNS = ['record_id', 'partition_id'];

    private bool $quarantined = false;

    /** @mago-expect lint:excessive-parameter-list */
    public function __construct(
        private Connection $connection,
        private PostgreSqlAuditOutboxSchema $schema,
        private ClockInterface $clock,
        private Uuidv7Generator $uuids,
        private AuditRecordValidator $validator,
        private AuditRecordFinalizer $finalizer,
        private AuditPartitionIdDeriver $partitions,
        private \BlackOps\Internal\Audit\Protection\AuditIngressEnvelopeCodec $envelopes,
        private AuditProtectedBodyCodec $protection,
        private AuditOutboxIdempotencyKeyHasher $hasher,
        private PostgreSqlAuditOutboxRecordHydrator $hydrator,
        private AuditOutboxConfiguration $configuration,
    ) {
        $this->transition = new AuditOutboxTransition($configuration);
    }

    private AuditOutboxTransition $transition;

    /** @mago-expect lint:no-else-clause */
    public function appendDurable(
        #[\SensitiveParameter]
        AuditRecordDraft $draft,
        #[\SensitiveParameter]
        string $idempotencyKey,
    ): AuditOutboxAppendResult {
        $this->assertAutoCommit();
        [$tenant, $partitionId, $idempotencyHash] = $this->validateAppendInput($draft, $idempotencyKey);
        $ownsRoot = $this->enterTransaction();

        try {
            $this->lockPartition($partitionId);
            $state = $this->readState($partitionId, $tenant, false);
            $aggregate = $this->readRetainedAggregate($partitionId, $tenant);
            $this->assertRetainedBoundary($state, $aggregate, $tenant, $partitionId);
            if ($state['missing']) {
                $this->assertNoOrphanRows($partitionId);
            }

            $existing = $this->findByIdempotency($partitionId, $idempotencyHash);
            if ($existing !== null) {
                $record = $this->hydrateRecord($existing);
                $storedEnvelope = $this->authenticateRecord($record, $tenant, $partitionId);
                $result = $this->existingAppendResult($draft, $idempotencyKey, $record, $storedEnvelope);
            } else {
                $this->assertAppendCapacity($state);
                $assignedAt = $this->assignedAt($state, $this->canonicalNow());
                $recordId = $this->generateUuid($this->date($assignedAt));
                $this->lockRecord($recordId);
                $this->assertRecordAvailable($recordId);

                $logical = $this->finalize($draft, new AuditRecordStamp($recordId, $assignedAt));
                $ordinal = $state['missing'] ? 1 : $state['lastIngressOrdinal'] + 1;
                $envelope = $this->createEnvelope($logical, $ordinal, $idempotencyKey, $partitionId, $tenant);
                $ingress = $this->protect($envelope, $partitionId, $ordinal, $idempotencyHash);
                $snapshot = AuditOutboxStateSnapshot::pending($assignedAt, $this->configuration);
                $record = AuditOutboxRecord::fromParts($ingress, $snapshot);
                $this->writeAppend($state, $record, $assignedAt);
                $result = new AuditOutboxAppendResult($record, true);
            }

            if ($ownsRoot) {
                try {
                    $this->connection->commit();
                } catch (\Throwable) {
                    $ownsRoot = false;
                    $this->rollbackOwned();
                    $this->quarantine();
                    throw $this->storageFailure();
                }
            }

            return $result;
        } catch (\Throwable $failure) {
            $safe = $this->safeFailure($failure);
            if ($ownsRoot) {
                $rollbackFailure = $this->storageFailure();
                if (!$this->rollbackOwned()) {
                    throw $rollbackFailure;
                }
            }

            throw $safe;
        }
    }

    /** @mago-expect lint:no-redundant-string-concat */
    public function find(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
    ): AuditOutboxLookupResult {
        $this->assertAutoCommit();
        $this->assertReadTransactionState();
        $this->assertRecordId($recordId);
        $this->assertPartitionBinding($expectedTenant, $expectedPartitionId);

        try {
            $row = $this->selectRecord(
                'record_id = CAST(:record_id AS uuid) AND partition_id = :partition_id' . ' AND '
                    . $this->tenantPredicate($expectedTenant),
                $this->recordParameters($recordId, $expectedPartitionId, $expectedTenant),
                forUpdate: false,
            );
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
        if ($row === null) {
            return AuditOutboxLookupResult::notFound();
        }

        try {
            $record = $this->hydrateRecord($row);
            $this->authenticateRecord($record, $expectedTenant, $expectedPartitionId);
        } catch (AuditOutboxStoreException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw $this->integrityFailure();
        }

        return AuditOutboxLookupResult::fromRecord($record);
    }

    public function claimNext(): AuditOutboxLookupResult
    {
        $this->assertAutoCommit();
        $ownsRoot = $this->enterTransaction();

        try {
            $trustedNow = $this->canonicalNow();
            $candidate = $this->candidate('pending', $trustedNow);
            if ($candidate === null) {
                $this->finishOwned($ownsRoot);

                return AuditOutboxLookupResult::notFound();
            }
            $partitionId = $this->candidatePartition($candidate);
            $recordId = $this->candidateRecordId($candidate);
            $this->lockPartition($partitionId);
            $state = $this->readState($partitionId, null, true);
            if ($state['missing'] || $state['tenant'] === null && $partitionId !== 'system:global') {
                throw $this->integrityFailure();
            }
            $row = $this->selectHeadRecord(
                $recordId,
                $partitionId,
                $state['tenant'],
                'pending',
                $trustedNow,
                forUpdate: true,
            );
            if ($row === null) {
                $this->finishOwned($ownsRoot);

                return AuditOutboxLookupResult::notFound();
            }
            $record = $this->hydrateRecord($row);
            $this->authenticateRecord($record, $state['tenant'], $partitionId);
            $token = $this->generateUuid($this->date($trustedNow));
            $next = $this->transition->claim(
                $record->stateSnapshot(),
                $record->stateSnapshot()->version(),
                $token,
                $trustedNow,
            );
            $this->applyCas(
                $record,
                $state['tenant'],
                $partitionId,
                $trustedNow,
                'pending',
                null,
                $next,
                requireExpiredLease: false,
            );
            $result = AuditOutboxRecord::fromParts($record->protectedIngress(), $next);
            $this->finishOwned($ownsRoot);

            return AuditOutboxLookupResult::fromRecord($result);
        } catch (\Throwable $failure) {
            $safe = $this->safeFailure($failure);
            if ($ownsRoot && !$this->rollbackOwned()) {
                throw $this->storageFailure();
            }

            throw $safe;
        }
    }

    public function recoverNextExpired(): AuditOutboxLookupResult
    {
        $this->assertAutoCommit();
        $ownsRoot = $this->enterTransaction();

        try {
            $trustedNow = $this->canonicalNow();
            $candidate = $this->candidate('leased', $trustedNow);
            if ($candidate === null) {
                $this->finishOwned($ownsRoot);

                return AuditOutboxLookupResult::notFound();
            }
            $partitionId = $this->candidatePartition($candidate);
            $recordId = $this->candidateRecordId($candidate);
            $this->lockPartition($partitionId);
            $state = $this->readState($partitionId, null, true);
            if ($state['missing'] || $state['tenant'] === null && $partitionId !== 'system:global') {
                throw $this->integrityFailure();
            }
            $row = $this->selectHeadRecord(
                $recordId,
                $partitionId,
                $state['tenant'],
                'leased',
                $trustedNow,
                forUpdate: true,
            );
            if ($row === null) {
                $this->finishOwned($ownsRoot);

                return AuditOutboxLookupResult::notFound();
            }
            $record = $this->hydrateRecord($row);
            $this->authenticateRecord($record, $state['tenant'], $partitionId);
            $expectedToken = $record->stateSnapshot()->leaseFencingToken();
            if ($expectedToken === null) {
                throw $this->integrityFailure();
            }
            $next = $this->transition->recover(
                $record->stateSnapshot(),
                $record->stateSnapshot()->version(),
                $expectedToken,
                $trustedNow,
            );
            $this->applyCas(
                $record,
                $state['tenant'],
                $partitionId,
                $trustedNow,
                'leased',
                $expectedToken,
                $next,
                requireExpiredLease: true,
            );
            $result = AuditOutboxRecord::fromParts($record->protectedIngress(), $next);
            $this->finishOwned($ownsRoot);

            return AuditOutboxLookupResult::fromRecord($result);
        } catch (\Throwable $failure) {
            $safe = $this->safeFailure($failure);
            if ($ownsRoot && !$this->rollbackOwned()) {
                throw $this->storageFailure();
            }

            throw $safe;
        }
    }

    public function retry(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
        #[\SensitiveParameter]
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
    ): AuditOutboxRecord {
        return $this->settleLeased(
            $recordId,
            $expectedTenant,
            $expectedPartitionId,
            $expectedVersion,
            $expectedToken,
            1,
        );
    }

    public function relay(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
        #[\SensitiveParameter]
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
    ): AuditOutboxRecord {
        return $this->settleLeased(
            $recordId,
            $expectedTenant,
            $expectedPartitionId,
            $expectedVersion,
            $expectedToken,
            2,
        );
    }

    public function poison(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
        #[\SensitiveParameter]
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
    ): AuditOutboxRecord {
        return $this->settleLeased(
            $recordId,
            $expectedTenant,
            $expectedPartitionId,
            $expectedVersion,
            $expectedToken,
            3,
        );
    }

    /** @mago-expect lint:excessive-parameter-list */
    private function settleLeased(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
        #[\SensitiveParameter]
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
        #[\SensitiveParameter]
        int $operation,
    ): AuditOutboxRecord {
        $this->assertAutoCommit();
        $this->assertRecordId($recordId);
        $this->assertExpectedVersion($expectedVersion);
        $this->assertToken($expectedToken);
        $this->assertPartitionBinding($expectedTenant, $expectedPartitionId);
        $ownsRoot = $this->enterTransaction();

        try {
            $this->lockPartition($expectedPartitionId);
            $state = $this->readState($expectedPartitionId, $expectedTenant, false);
            if ($state['missing']) {
                throw $this->transitionFailure();
            }
            $row = $this->selectHeadRecord(
                $recordId,
                $expectedPartitionId,
                $expectedTenant,
                'leased',
                null,
                forUpdate: true,
                expectedVersion: $expectedVersion,
                expectedToken: $expectedToken,
            );
            if ($row === null) {
                throw $this->transitionFailure();
            }
            $record = $this->hydrateRecord($row);
            $this->authenticateRecord($record, $expectedTenant, $expectedPartitionId);
            $trustedNow = $this->canonicalNow();
            $snapshot = $record->stateSnapshot();
            $next = match ($operation) {
                1 => $this->transition->retry($snapshot, $expectedVersion, $expectedToken, $trustedNow),
                2 => $this->transition->relay($snapshot, $expectedVersion, $expectedToken, $trustedNow),
                3 => $this->transition->poison($snapshot, $expectedVersion, $expectedToken, $trustedNow),
                default => throw $this->integrityFailure(),
            };
            $this->applyCas(
                $record,
                $expectedTenant,
                $expectedPartitionId,
                $trustedNow,
                'leased',
                $expectedToken,
                $next,
                requireExpiredLease: false,
            );
            $result = AuditOutboxRecord::fromParts($record->protectedIngress(), $next);
            $this->finishOwned($ownsRoot);

            return $result;
        } catch (\Throwable $failure) {
            $safe = $this->safeFailure($failure);
            if ($ownsRoot && !$this->rollbackOwned()) {
                throw $this->storageFailure();
            }

            throw $safe;
        }
    }

    /** @return array{0:?AuditTypedReference,1:string,2:string} */
    private function validateAppendInput(
        #[\SensitiveParameter]
        AuditRecordDraft $draft,
        #[\SensitiveParameter]
        string $idempotencyKey,
    ): array {
        try {
            $this->validator->validateDraft($draft);
        } catch (\Throwable) {
            throw $this->integrityFailure();
        }
        if ($draft->policy->auditDelivery->value !== 'durable') {
            throw $this->integrityFailure();
        }
        try {
            $partitionId = $this->partitions->derive($draft->tenant);
            if (!is_string($partitionId) || preg_match(self::PARTITION_PATTERN, $partitionId) !== 1) {
                throw new \RuntimeException();
            }
            $hash = $this->hasher->hash($partitionId, $idempotencyKey);
        } catch (AuditOutboxStoreException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw $this->integrityFailure();
        }

        return [$draft->tenant, $partitionId, $hash];
    }

    /**
     * @return array{missing:bool,partitionId:string,tenant:?AuditTypedReference,lastIngressOrdinal:int,lastAssignedAt:?string,stateVersion:int}
     * @mago-expect lint:no-boolean-flag-parameter
     */
    private function readState(
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        bool $allowAnyTenant,
    ): array {
        try {
            $row = $this->connection->fetchAssociative(
                'SELECT partition_id, tenant_type, tenant_id, last_ingress_ordinal, '
                . "to_char(last_assigned_recorded_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') AS last_assigned_recorded_at, "
                . 'state_version FROM '
                . $this->schema->partitionStateTable()
                . ' WHERE partition_id = :partition_id FOR UPDATE',
                ['partition_id' => $partitionId],
                ['partition_id' => ParameterType::STRING],
            );
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
        if ($row === false) {
            return [
                'missing' => true,
                'partitionId' => $partitionId,
                'tenant' => null,
                'lastIngressOrdinal' => 0,
                'lastAssignedAt' => null,
                'stateVersion' => 0,
            ];
        }
        if (
            !is_array($row)
            || array_keys($row) !== [
                'partition_id',
                'tenant_type',
                'tenant_id',
                'last_ingress_ordinal',
                'last_assigned_recorded_at',
                'state_version',
            ]
        ) {
            throw $this->integrityFailure();
        }
        $actualPartition = $this->string($row['partition_id']);
        if ($actualPartition !== $partitionId || preg_match(self::PARTITION_PATTERN, $actualPartition) !== 1) {
            throw $this->integrityFailure();
        }
        $tenant = $this->tenantFromColumns($row['tenant_type'], $row['tenant_id']);
        if (!$allowAnyTenant && $tenant?->toArray() !== $expectedTenant?->toArray()) {
            throw $this->integrityFailure();
        }
        if ($this->partitionForTenant($tenant) !== $partitionId) {
            throw $this->integrityFailure();
        }
        $ordinal = $this->integer($row['last_ingress_ordinal'], 1, self::MAX_SAFE_INTEGER);
        $assignedAt = $this->string($row['last_assigned_recorded_at']);
        $this->assertTimestamp($assignedAt);
        $version = $this->integer($row['state_version'], 1, self::MAX_SAFE_INTEGER);
        if ($version !== $ordinal) {
            throw $this->integrityFailure();
        }

        return [
            'missing' => false,
            'partitionId' => $partitionId,
            'tenant' => $tenant,
            'lastIngressOrdinal' => $ordinal,
            'lastAssignedAt' => $assignedAt,
            'stateVersion' => $version,
        ];
    }

    /** @return array{rowCount:int,minOrdinal:?int,maxOrdinal:?int,tenantMismatchCount:int} */
    private function readRetainedAggregate(
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
    ): array {
        try {
            $row = $this->connection->fetchAssociative(
                'SELECT count(*) AS row_count, min(ingress_ordinal) AS min_ordinal, '
                . 'max(ingress_ordinal) AS max_ordinal, count(*) FILTER (WHERE tenant_type IS DISTINCT FROM :expected_tenant_type '
                . 'OR tenant_id IS DISTINCT FROM :expected_tenant_id) AS tenant_mismatch_count FROM '
                . $this->schema->recordsTable()
                . ' WHERE partition_id = :partition_id',
                [
                    'expected_tenant_type' => $tenant?->type,
                    'expected_tenant_id' => $tenant?->id,
                    'partition_id' => $partitionId,
                ],
                [
                    'expected_tenant_type' => ParameterType::STRING,
                    'expected_tenant_id' => ParameterType::STRING,
                    'partition_id' => ParameterType::STRING,
                ],
            );
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
        if (
            !is_array($row)
            || array_keys($row) !== ['row_count', 'min_ordinal', 'max_ordinal', 'tenant_mismatch_count']
        ) {
            throw $this->integrityFailure();
        }
        $count = $this->integer($row['row_count'], 0, self::MAX_SAFE_INTEGER);
        $min = $row['min_ordinal'] === null ? null : $this->integer($row['min_ordinal'], 1, self::MAX_SAFE_INTEGER);
        $max = $row['max_ordinal'] === null ? null : $this->integer($row['max_ordinal'], 1, self::MAX_SAFE_INTEGER);
        $mismatch = $this->integer($row['tenant_mismatch_count'], 0, self::MAX_SAFE_INTEGER);
        if ($count === 0 && ($min !== null || $max !== null) || $count > 0 && ($min === null || $max === null)) {
            throw $this->integrityFailure();
        }

        return ['rowCount' => $count, 'minOrdinal' => $min, 'maxOrdinal' => $max, 'tenantMismatchCount' => $mismatch];
    }

    /** @param array{missing:bool,partitionId:string,tenant:?AuditTypedReference,lastIngressOrdinal:int,lastAssignedAt:?string,stateVersion:int} $state @param array{rowCount:int,minOrdinal:?int,maxOrdinal:?int,tenantMismatchCount:int} $aggregate */
    private function assertRetainedBoundary(
        #[\SensitiveParameter]
        array $state,
        #[\SensitiveParameter]
        array $aggregate,
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        #[\SensitiveParameter]
        string $partitionId,
    ): void {
        if ($state['missing']) {
            if (
                $aggregate !== ['rowCount' => 0, 'minOrdinal' => null, 'maxOrdinal' => null, 'tenantMismatchCount' => 0]
            ) {
                throw $this->integrityFailure();
            }

            return;
        }
        if (
            $state['partitionId'] !== $partitionId
            || $state['tenant']?->toArray() !== $tenant?->toArray()
            || $aggregate !== [
                'rowCount' => $state['lastIngressOrdinal'],
                'minOrdinal' => 1,
                'maxOrdinal' => $state['lastIngressOrdinal'],
                'tenantMismatchCount' => 0,
            ]
        ) {
            throw $this->integrityFailure();
        }
    }

    /** @param array{missing:bool,partitionId:string,tenant:?AuditTypedReference,lastIngressOrdinal:int,lastAssignedAt:?string,stateVersion:int} $state */
    private function assertAppendCapacity(#[\SensitiveParameter] array $state): void
    {
        if (
            !$state['missing']
            && (
                $state['lastIngressOrdinal'] >= self::MAX_SAFE_INTEGER
                || $state['stateVersion'] >= self::MAX_SAFE_INTEGER
            )
        ) {
            throw $this->resourceFailure();
        }
    }

    /** @return array<array-key,mixed>|null */
    private function findByIdempotency(
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $idempotencyHash,
    ): ?array {
        return $this->selectRecord(
            'partition_id = :partition_id AND idempotency_key_hash = :idempotency_key_hash',
            [
                'partition_id' => $partitionId,
                'idempotency_key_hash' => $idempotencyHash,
            ],
            forUpdate: false,
        );
    }

    /** @param array<string,mixed> $parameters @return array<array-key,mixed>|null */
    private function selectRecord(
        #[\SensitiveParameter]
        string $where,
        #[\SensitiveParameter]
        array $parameters,
        bool $forUpdate,
    ): ?array {
        $types = [];
        foreach (array_keys($parameters) as $parameter) {
            $types[$parameter] = ParameterType::STRING;
        }
        try {
            $rows = $this->connection->fetchAllAssociative(
                $this->recordSelectSql($where, $forUpdate),
                $parameters,
                $types,
            );
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
        if (!is_array($rows)) {
            throw $this->integrityFailure();
        }
        if ($rows === []) {
            return null;
        }
        if (!array_is_list($rows) || count($rows) !== 1 || !is_array($rows[0])) {
            throw $this->integrityFailure();
        }
        if (array_keys($rows[0]) !== self::RECORD_COLUMNS) {
            throw $this->integrityFailure();
        }

        return $rows[0];
    }

    /** @mago-expect lint:no-boolean-flag-parameter */
    private function recordSelectSql(#[\SensitiveParameter] string $where, bool $forUpdate): string
    {
        $suffix = $forUpdate ? ' FOR UPDATE' : '';

        return (
            'SELECT schema_version, kind, record_id::text AS record_id, partition_id, tenant_type, tenant_id, '
            . 'ingress_ordinal, idempotency_key_hash, content_hash, state, attempt, '
            . "CASE WHEN available_at IS NULL THEN NULL ELSE to_char(available_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') END AS available_at, "
            . 'lease_fencing_token::text AS lease_fencing_token, '
            . "CASE WHEN lease_expires_at IS NULL THEN NULL ELSE to_char(lease_expires_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') END AS lease_expires_at, "
            . 'protected_body, version FROM '
            . $this->schema->recordsTable()
            . ' AS outbox_row WHERE '
            . $where
            . $suffix
        );
    }

    /** @return array<string,mixed> */
    private function recordParameters(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
    ): array {
        $parameters = [
            'record_id' => $recordId,
            'partition_id' => $partitionId,
        ];
        if ($tenant !== null) {
            $parameters['tenant_type'] = $tenant->type;
            $parameters['tenant_id'] = $tenant->id;
        }

        return $parameters;
    }

    private function tenantPredicate(#[\SensitiveParameter] ?AuditTypedReference $tenant): string
    {
        return $tenant === null
            ? 'tenant_type IS NULL AND tenant_id IS NULL'
            : 'tenant_type = :tenant_type AND tenant_id = :tenant_id';
    }

    /** @return array<array-key,mixed>|null */
    private function candidate(#[\SensitiveParameter] string $state, #[\SensitiveParameter] string $trustedNow): ?array
    {
        $order = $state === 'pending' ? 'r.available_at ASC' : 'r.lease_expires_at ASC';
        $timeColumn = $state === 'pending' ? 'r.available_at' : 'r.lease_expires_at';
        try {
            $rows = $this->connection->fetchAllAssociative(
                "SELECT r.record_id::text AS record_id, r.partition_id FROM {$this->schema->recordsTable()} r "
                . "WHERE r.state = :state AND {$timeColumn} <= CAST(:trusted_now AS timestamptz) "
                . 'AND NOT EXISTS (SELECT 1 FROM '
                . $this->schema->recordsTable()
                . ' lower_row '
                . "WHERE lower_row.partition_id = r.partition_id AND lower_row.state IN ('pending', 'leased') "
                . 'AND lower_row.ingress_ordinal < r.ingress_ordinal) '
                . "ORDER BY {$order}, r.partition_id ASC, r.ingress_ordinal ASC LIMIT 1",
                ['state' => $state, 'trusted_now' => $trustedNow],
                ['state' => ParameterType::STRING, 'trusted_now' => ParameterType::STRING],
            );
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
        if (!is_array($rows) || $rows === []) {
            return null;
        }
        if (
            !array_is_list($rows)
            || count($rows) !== 1
            || !is_array($rows[0])
            || array_keys($rows[0]) !== self::CANDIDATE_COLUMNS
        ) {
            throw $this->integrityFailure();
        }

        return $rows[0];
    }

    /** @param array<array-key,mixed> $candidate */
    private function candidatePartition(#[\SensitiveParameter] array $candidate): string
    {
        if (array_keys($candidate) !== self::CANDIDATE_COLUMNS) {
            throw $this->integrityFailure();
        }
        $partition = $this->string($candidate['partition_id']);
        if (preg_match(self::PARTITION_PATTERN, $partition) !== 1) {
            throw $this->integrityFailure();
        }

        return $partition;
    }

    /** @param array<array-key,mixed> $candidate */
    private function candidateRecordId(#[\SensitiveParameter] array $candidate): string
    {
        $recordId = $this->string($candidate['record_id'] ?? null);
        $this->assertRecordId($recordId);

        return $recordId;
    }

    /**
     * @return array<array-key,mixed>|null
     * @mago-expect lint:excessive-parameter-list
     */
    private function selectHeadRecord(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        #[\SensitiveParameter]
        string $state,
        #[\SensitiveParameter]
        ?string $trustedNow,
        bool $forUpdate,
        #[\SensitiveParameter]
        ?int $expectedVersion = null,
        #[\SensitiveParameter]
        ?string $expectedToken = null,
    ): ?array {
        $where =
            'record_id = CAST(:record_id AS uuid) AND partition_id = :partition_id AND state = :state '
            . 'AND '
            . $this->tenantPredicate($tenant)
            . ' AND NOT EXISTS (SELECT 1 FROM '
            . $this->schema->recordsTable()
            . ' lower_row '
            . "WHERE lower_row.partition_id = :partition_id AND lower_row.state IN ('pending', 'leased') "
            . 'AND lower_row.ingress_ordinal < outbox_row.ingress_ordinal)';
        $parameters = [
            'record_id' => $recordId,
            'partition_id' => $partitionId,
            'state' => $state,
        ];
        if ($tenant !== null) {
            $parameters['tenant_type'] = $tenant->type;
            $parameters['tenant_id'] = $tenant->id;
        }
        if ($trustedNow !== null) {
            $where .= $state === 'pending'
                ? ' AND available_at <= CAST(:trusted_now AS timestamptz)'
                : ' AND lease_expires_at <= CAST(:trusted_now AS timestamptz)';
            $parameters['trusted_now'] = $trustedNow;
        }
        if ($expectedVersion !== null) {
            $where .= ' AND version = CAST(:expected_version AS bigint) AND lease_fencing_token = CAST(:expected_token AS uuid)';
            $parameters['expected_version'] = $expectedVersion;
            $parameters['expected_token'] = $expectedToken;
        }

        return $this->selectRecord($where, $parameters, $forUpdate);
    }

    private function lockPartition(#[\SensitiveParameter] string $partitionId): void
    {
        try {
            $this->connection->fetchOne(
                'SELECT pg_advisory_xact_lock(hashtextextended(:lock_key, 0))',
                [
                    'lock_key' =>
                        'blackops.audit.outbox.partition.v1:' . $this->schema->schemaName() . ':' . $partitionId,
                ],
                ['lock_key' => ParameterType::STRING],
            );
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
    }

    private function lockRecord(#[\SensitiveParameter] string $recordId): void
    {
        try {
            $this->connection->fetchOne(
                'SELECT pg_advisory_xact_lock(hashtextextended(:lock_key, 0))',
                ['lock_key' => 'blackops.audit.outbox.record.v1:' . $this->schema->schemaName() . ':' . $recordId],
                ['lock_key' => ParameterType::STRING],
            );
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
    }

    private function assertRecordAvailable(#[\SensitiveParameter] string $recordId): void
    {
        try {
            $exists = $this->connection->fetchOne(
                'SELECT 1 FROM '
                . $this->schema->recordsTable()
                . ' WHERE record_id = CAST(:record_id AS uuid) LIMIT 1',
                ['record_id' => $recordId],
                ['record_id' => ParameterType::STRING],
            );
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
        if ($exists !== false && $exists !== null && (string) $exists !== '0') {
            throw $this->resourceFailure();
        }
    }

    private function assertNoOrphanRows(#[\SensitiveParameter] string $partitionId): void
    {
        try {
            $exists = $this->connection->fetchOne(
                'SELECT 1 FROM ' . $this->schema->recordsTable() . ' WHERE partition_id = :partition_id LIMIT 1',
                ['partition_id' => $partitionId],
                ['partition_id' => ParameterType::STRING],
            );
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
        if ($exists !== false && $exists !== null && (string) $exists !== '0') {
            throw $this->integrityFailure();
        }
    }

    private function hydrateRecord(#[\SensitiveParameter] array $row): AuditOutboxRecord
    {
        try {
            return $this->hydrator->hydrate($row, $this->configuration);
        } catch (AuditOutboxStoreException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw $this->integrityFailure();
        }
    }

    private function authenticateRecord(
        #[\SensitiveParameter]
        AuditOutboxRecord $record,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
    ): AuditIngressEnvelope {
        try {
            $ingress = $record->protectedIngress();
            $value = $ingress->toArray();
            $tenant = $this->tenantFromValue($value['tenantBinding'] ?? null);
            if ($tenant?->toArray() !== $expectedTenant?->toArray()) {
                throw new \RuntimeException();
            }
            if (
                $this->partitionForTenant($tenant) !== $expectedPartitionId
                || $value['partitionId'] !== $expectedPartitionId
            ) {
                throw new \RuntimeException();
            }

            return $this->protection->unprotectOutbox($ingress);
        } catch (AuditOutboxStoreException $failure) {
            throw $failure;
        } catch (AuditProtectionException) {
            throw $this->integrityFailure();
        } catch (\Throwable) {
            throw $this->integrityFailure();
        }
    }

    private function existingAppendResult(
        #[\SensitiveParameter]
        AuditRecordDraft $draft,
        #[\SensitiveParameter]
        string $idempotencyKey,
        #[\SensitiveParameter]
        AuditOutboxRecord $record,
        #[\SensitiveParameter]
        AuditIngressEnvelope $storedEnvelope,
    ): AuditOutboxAppendResult {
        if ($storedEnvelope->idempotencyKey() !== $idempotencyKey) {
            throw $this->idempotencyFailure();
        }
        $stored = $storedEnvelope->toArray();
        /** @var array{schemaVersion:int,kind:string,logicalRecord:array<string,mixed>,contentHash:string,recordedAt:string,ingressOrdinal:int,idempotencyKey:string,recordId:string,partitionId:string,tenantBinding:?array{type:string,id:string}} $stored */
        if (
            !is_string($stored['recordId'] ?? null)
            || !is_string($stored['recordedAt'] ?? null)
            || !is_int($stored['ingressOrdinal'] ?? null)
        ) {
            throw $this->integrityFailure();
        }
        $logical = $this->finalize($draft, new AuditRecordStamp($stored['recordId'], $stored['recordedAt']));
        $candidate = $this->createEnvelope(
            $logical,
            $stored['ingressOrdinal'],
            $idempotencyKey,
            $stored['partitionId'],
            $this->tenantFromValue($stored['tenantBinding'] ?? null),
        );
        if ($this->envelopes->encode($candidate) !== $this->envelopes->encode($storedEnvelope)) {
            throw $this->idempotencyFailure();
        }

        return new AuditOutboxAppendResult($record, false);
    }

    private function finalize(
        #[\SensitiveParameter]
        AuditRecordDraft $draft,
        #[\SensitiveParameter]
        AuditRecordStamp $stamp,
    ): \BlackOps\Internal\Audit\Record\CanonicalAuditLogicalRecord {
        try {
            $logical = $this->finalizer->finalize($draft, $stamp);
            $value = $logical->toArray();
            if (
                $logical->definition()->delivery->value !== 'durable'
                || ($value['policy']['auditDelivery'] ?? null) !== 'durable'
                || ($value['recordId'] ?? null) !== $stamp->recordId
                || ($value['time']['recordedAt'] ?? null) !== $stamp->recordedAt
            ) {
                throw new \RuntimeException();
            }

            return $logical;
        } catch (AuditValidationException $failure) {
            if ($failure->failure() === AuditValidationFailure::JcsBoundInvalid) {
                throw $this->resourceFailure();
            }
            throw $this->integrityFailure();
        } catch (AuditOutboxStoreException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw $this->integrityFailure();
        }
    }

    private function createEnvelope(
        #[\SensitiveParameter]
        \BlackOps\Internal\Audit\Record\CanonicalAuditLogicalRecord $logical,
        #[\SensitiveParameter]
        int $ordinal,
        #[\SensitiveParameter]
        string $idempotencyKey,
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
    ): AuditIngressEnvelope {
        try {
            $envelope = $this->envelopes->create($logical, $ordinal, $idempotencyKey);
            $value = $envelope->toArray();
            if (
                $value['partitionId'] !== $partitionId
                || $value['tenantBinding'] !== $tenant?->toArray()
                || $value['ingressOrdinal'] !== $ordinal
                || $envelope->idempotencyKey() !== $idempotencyKey
            ) {
                throw new \RuntimeException();
            }

            return $envelope;
        } catch (AuditOutboxStoreException $failure) {
            throw $failure;
        } catch (AuditValidationException) {
            throw $this->integrityFailure();
        } catch (\Throwable) {
            throw $this->integrityFailure();
        }
    }

    private function protect(
        #[\SensitiveParameter]
        AuditIngressEnvelope $envelope,
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        int $ordinal,
        #[\SensitiveParameter]
        string $idempotencyHash,
    ): AuditProtectedIngress {
        try {
            $ingress = $this->protection->protectOutbox($envelope);
            $value = $ingress->toArray();
            $expected = $envelope->toArray();
            if (
                $value['partitionId'] !== $partitionId
                || $value['ingressOrdinal'] !== $ordinal
                || $value['idempotencyKeyHash'] !== $idempotencyHash
                || $value['contentHash'] !== $expected['contentHash']
            ) {
                throw new \RuntimeException();
            }

            return $ingress;
        } catch (AuditOutboxStoreException $failure) {
            throw $failure;
        } catch (AuditProtectionException) {
            throw $this->resourceFailure();
        } catch (\Throwable) {
            throw $this->resourceFailure();
        }
    }

    /**
     * @param array{missing:bool,partitionId:string,tenant:?AuditTypedReference,lastIngressOrdinal:int,lastAssignedAt:?string,stateVersion:int} $state
     * @mago-expect lint:no-else-clause
     */
    private function writeAppend(
        #[\SensitiveParameter]
        array $state,
        #[\SensitiveParameter]
        AuditOutboxRecord $record,
        #[\SensitiveParameter]
        string $assignedAt,
    ): void {
        $ingress = $record->protectedIngress()->toArray();
        $snapshot = $record->stateSnapshot()->toArray();
        $tenant = $ingress['tenantBinding'] ?? null;
        $parameters = [
            'partition_id' => $ingress['partitionId'],
            'tenant_type' => is_array($tenant) ? $tenant['type'] ?? null : null,
            'tenant_id' => is_array($tenant) ? $tenant['id'] ?? null : null,
            'schema_version' => 1,
            'kind' => 'audit_outbox_record',
            'record_id' => $ingress['recordId'],
            'idempotency_key_hash' => $ingress['idempotencyKeyHash'],
            'content_hash' => $ingress['contentHash'],
            'state' => $snapshot['state'],
            'attempt' => $snapshot['attempt'],
            'available_at' => $snapshot['availableAt'],
            'lease_fencing_token' => $snapshot['leaseFencingToken'],
            'lease_expires_at' => $snapshot['leaseExpiresAt'],
            'protected_body' => $ingress['protectedBody'],
            'version' => $snapshot['version'],
        ];
        $types = [
            'partition_id' => ParameterType::STRING,
            'tenant_type' => ParameterType::STRING,
            'tenant_id' => ParameterType::STRING,
            'schema_version' => ParameterType::INTEGER,
            'kind' => ParameterType::STRING,
            'record_id' => ParameterType::STRING,
            'idempotency_key_hash' => ParameterType::STRING,
            'content_hash' => ParameterType::STRING,
            'state' => ParameterType::STRING,
            'attempt' => ParameterType::INTEGER,
            'available_at' => ParameterType::STRING,
            'lease_fencing_token' => ParameterType::STRING,
            'lease_expires_at' => ParameterType::STRING,
            'protected_body' => ParameterType::BINARY,
            'version' => ParameterType::INTEGER,
        ];
        if ($state['missing']) {
            $parameters['next_ordinal'] = $ingress['ingressOrdinal'];
            $parameters['assigned_at'] = $assignedAt;
            $types['next_ordinal'] = ParameterType::INTEGER;
            $types['assigned_at'] = ParameterType::STRING;
            $sql = $this->bootstrapAppendSql();
        } else {
            $parameters['previous_ordinal'] = $state['lastIngressOrdinal'];
            $parameters['previous_assigned_at'] = $state['lastAssignedAt'] ?? $assignedAt;
            $parameters['previous_version'] = $state['stateVersion'];
            $parameters['next_ordinal'] = $ingress['ingressOrdinal'];
            $parameters['assigned_at'] = $assignedAt;
            $types['previous_ordinal'] = ParameterType::INTEGER;
            $types['previous_assigned_at'] = ParameterType::STRING;
            $types['previous_version'] = ParameterType::INTEGER;
            $types['next_ordinal'] = ParameterType::INTEGER;
            $types['assigned_at'] = ParameterType::STRING;
            $sql = $this->existingAppendSql();
        }
        try {
            $result = $this->connection->fetchOne($sql, $parameters, $types);
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
        if ((string) $result !== '1') {
            throw $this->storageFailure();
        }
    }

    private function bootstrapAppendSql(): string
    {
        $state = $this->schema->partitionStateTable();
        $records = $this->schema->recordsTable();

        return "WITH inserted_state AS (
                INSERT INTO {$state} (
                    partition_id, tenant_type, tenant_id, last_ingress_ordinal,
                    last_assigned_recorded_at, state_version
                )
                SELECT :partition_id, :tenant_type, :tenant_id,
                       CAST(:next_ordinal AS bigint), CAST(:assigned_at AS timestamptz), 1
                RETURNING partition_id
            ),
            inserted_record AS (
                INSERT INTO {$records} (
                    schema_version, kind, record_id, partition_id, tenant_type, tenant_id,
                    ingress_ordinal, idempotency_key_hash, content_hash, state, attempt,
                    available_at, lease_fencing_token, lease_expires_at, protected_body, version
                )
                SELECT CAST(:schema_version AS smallint), :kind, CAST(:record_id AS uuid),
                       :partition_id, :tenant_type, :tenant_id, CAST(:next_ordinal AS bigint),
                       :idempotency_key_hash, :content_hash, :state, CAST(:attempt AS smallint),
                       CAST(:available_at AS timestamptz), CAST(:lease_fencing_token AS uuid),
                       CAST(:lease_expires_at AS timestamptz), CAST(:protected_body AS bytea),
                       CAST(:version AS bigint)
                FROM inserted_state
                RETURNING partition_id
            ),
            fence AS (
                SELECT CAST(CASE WHEN (
                    (SELECT count(*) FROM inserted_record) = 1
                    AND (SELECT count(*) FROM inserted_state) = 1
                ) THEN '1' ELSE 'audit_outbox_abort' END AS bigint) AS committed
            )
            SELECT committed FROM fence";
    }

    private function existingAppendSql(): string
    {
        $state = $this->schema->partitionStateTable();
        $records = $this->schema->recordsTable();

        return (
            'WITH '
            . $this->advancedStateCte($state)
            . ",
            inserted_record AS (
                INSERT INTO {$records} (
                    schema_version, kind, record_id, partition_id, tenant_type, tenant_id,
                    ingress_ordinal, idempotency_key_hash, content_hash, state, attempt,
                    available_at, lease_fencing_token, lease_expires_at, protected_body, version
                )
                SELECT CAST(:schema_version AS smallint), :kind, CAST(:record_id AS uuid),
                       :partition_id, :tenant_type, :tenant_id, CAST(:next_ordinal AS bigint),
                       :idempotency_key_hash, :content_hash, :state, CAST(:attempt AS smallint),
                       CAST(:available_at AS timestamptz), CAST(:lease_fencing_token AS uuid),
                       CAST(:lease_expires_at AS timestamptz), CAST(:protected_body AS bytea),
                       CAST(:version AS bigint)
                FROM advanced_state
                RETURNING partition_id
            ),
            fence AS (
                SELECT CAST(CASE WHEN (
                    (SELECT count(*) FROM inserted_record) = 1
                    AND (SELECT count(*) FROM advanced_state) = 1
                ) THEN '1' ELSE 'audit_outbox_abort' END AS bigint) AS committed
            )
            SELECT committed FROM fence"
        );
    }

    private function advancedStateCte(#[\SensitiveParameter] string $state): string
    {
        return "advanced_state AS (
                UPDATE {$state}
                SET last_ingress_ordinal = CAST(:next_ordinal AS bigint),
                    last_assigned_recorded_at = CAST(:assigned_at AS timestamptz),
                    state_version = state_version + 1
                WHERE partition_id = :partition_id
                  AND tenant_type IS NOT DISTINCT FROM :tenant_type
                  AND tenant_id IS NOT DISTINCT FROM :tenant_id
                  AND last_ingress_ordinal = CAST(:previous_ordinal AS bigint)
                  AND last_assigned_recorded_at = CAST(:previous_assigned_at AS timestamptz)
                  AND state_version = CAST(:previous_version AS bigint)
                RETURNING partition_id
            )";
    }

    /**
     * @mago-expect lint:excessive-parameter-list
     * @mago-expect lint:no-boolean-flag-parameter
     * @mago-expect lint:no-nested-ternary
     */
    private function applyCas(
        #[\SensitiveParameter]
        AuditOutboxRecord $record,
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $trustedNow,
        #[\SensitiveParameter]
        string $expectedState,
        #[\SensitiveParameter]
        ?string $expectedToken,
        #[\SensitiveParameter]
        AuditOutboxStateSnapshot $next,
        bool $requireExpiredLease,
    ): void {
        $old = $record->stateSnapshot();
        $parameters = [
            'next_state' => $next->state()->value,
            'next_attempt' => $next->attempt(),
            'next_available_at' => $next->availableAt(),
            'next_lease_token' => $next->leaseFencingToken(),
            'next_lease_expires_at' => $next->leaseExpiresAt(),
            'next_version' => $next->version(),
            'record_id' => $record->protectedIngress()->toArray()['recordId'],
            'partition_id' => $partitionId,
            'tenant_type' => $tenant?->type,
            'tenant_id' => $tenant?->id,
            'expected_state' => $expectedState,
            'expected_version' => $old->version(),
            'expected_token' => $expectedToken,
            'expected_expiry' => $old->leaseExpiresAt(),
            'trusted_now' => $trustedNow,
        ];
        $types = [
            'next_state' => ParameterType::STRING,
            'next_attempt' => ParameterType::INTEGER,
            'next_available_at' => ParameterType::STRING,
            'next_lease_token' => ParameterType::STRING,
            'next_lease_expires_at' => ParameterType::STRING,
            'next_version' => ParameterType::INTEGER,
            'record_id' => ParameterType::STRING,
            'partition_id' => ParameterType::STRING,
            'tenant_type' => ParameterType::STRING,
            'tenant_id' => ParameterType::STRING,
            'expected_state' => ParameterType::STRING,
            'expected_version' => ParameterType::INTEGER,
            'expected_token' => ParameterType::STRING,
            'expected_expiry' => ParameterType::STRING,
            'trusted_now' => ParameterType::STRING,
        ];
        $eligibility = $requireExpiredLease
            ? "AND state = 'leased' AND lease_expires_at <= CAST(:trusted_now AS timestamptz) "
            : (
                $expectedState === 'leased'
                    ? "AND state = 'leased' AND lease_expires_at > CAST(:trusted_now AS timestamptz) "
                    : "AND state = 'pending' AND available_at <= CAST(:trusted_now AS timestamptz) "
            );
        try {
            $affected = $this->connection->executeStatement(
                'UPDATE '
                . $this->schema->recordsTable()
                . ' AS current_row'
                . ' SET state = :next_state, attempt = CAST(:next_attempt AS smallint), '
                . 'available_at = CAST(:next_available_at AS timestamptz), '
                . 'lease_fencing_token = CAST(:next_lease_token AS uuid), '
                . 'lease_expires_at = CAST(:next_lease_expires_at AS timestamptz), version = CAST(:next_version AS bigint) '
                . 'WHERE record_id = CAST(:record_id AS uuid) AND partition_id = :partition_id '
                . 'AND tenant_type IS NOT DISTINCT FROM :tenant_type AND tenant_id IS NOT DISTINCT FROM :tenant_id '
                . 'AND state = :expected_state AND version = CAST(:expected_version AS bigint) '
                . 'AND lease_fencing_token IS NOT DISTINCT FROM CAST(:expected_token AS uuid) '
                . 'AND lease_expires_at IS NOT DISTINCT FROM CAST(:expected_expiry AS timestamptz) '
                . $eligibility
                . 'AND NOT EXISTS (SELECT 1 FROM '
                . $this->schema->recordsTable()
                . ' lower_row '
                . "WHERE lower_row.partition_id = :partition_id AND lower_row.state IN ('pending', 'leased') "
                . 'AND lower_row.ingress_ordinal < current_row.ingress_ordinal)',
                $parameters,
                $types,
            );
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
        if ($affected === 0) {
            throw $this->transitionFailure();
        }
        if ($affected !== 1) {
            throw $this->integrityFailure();
        }
    }

    private function enterTransaction(): bool
    {
        $this->quarantined = false;
        try {
            $active = $this->connection->isTransactionActive();
            $level = $this->connection->getTransactionNestingLevel();
        } catch (\Throwable) {
            throw $this->storageFailure();
        }
        if (!$active && $level === 0) {
            try {
                $this->connection->beginTransaction();
            } catch (\Throwable) {
                $this->quarantine();
                throw $this->storageFailure();
            }

            return true;
        }
        if ($active && $level === 1) {
            return false;
        }

        throw $this->storageFailure();
    }

    private function assertReadTransactionState(): void
    {
        try {
            $active = $this->connection->isTransactionActive();
            $level = $this->connection->getTransactionNestingLevel();
        } catch (\Throwable) {
            throw $this->storageFailure();
        }
        if (!$active && $level !== 0 || $active && $level !== 1) {
            throw $this->storageFailure();
        }
    }

    /** @mago-expect lint:no-boolean-flag-parameter */
    private function finishOwned(bool &$ownsRoot): void
    {
        if (!$ownsRoot) {
            return;
        }
        try {
            $this->connection->commit();
        } catch (\Throwable) {
            $ownsRoot = false;
            $this->rollbackOwned();
            $this->quarantine();
            throw $this->storageFailure();
        }
        $ownsRoot = false;
    }

    private function rollbackOwned(): bool
    {
        try {
            $this->connection->rollBack();
        } catch (\Throwable) {
            $this->quarantine();

            return false;
        }

        return true;
    }

    /** @mago-expect lint:no-empty-catch-clause */
    private function quarantine(): void
    {
        if ($this->quarantined) {
            return;
        }
        $this->quarantined = true;
        try {
            $this->connection->close();
        } catch (\Throwable) {
        }
    }

    /** @mago-expect lint:no-else-clause */
    private function safeFailure(#[\SensitiveParameter] \Throwable $failure): AuditOutboxStoreException
    {
        if ($failure instanceof AuditOutboxStoreException) {
            return $failure;
        }
        $storeFailure = AuditOutboxStoreFailure::StorageUnavailable;
        if ($failure instanceof AuditProtectionException) {
            $storeFailure = AuditOutboxStoreFailure::ResourceUnavailable;
        } elseif ($failure instanceof \BlackOps\Internal\Audit\Delivery\AuditOutboxException) {
            $storeFailure = match ($failure->failure()->value) {
                'resource_unavailable' => AuditOutboxStoreFailure::ResourceUnavailable,
                'integrity_invalid' => AuditOutboxStoreFailure::IntegrityInvalid,
                default => AuditOutboxStoreFailure::TransitionConflict,
            };
        }

        unset($failure);
        return new AuditOutboxStoreException($storeFailure);
    }

    private function partitionForTenant(#[\SensitiveParameter] ?AuditTypedReference $tenant): string
    {
        try {
            $partition = $this->partitions->derive($tenant);
        } catch (\Throwable) {
            throw $this->integrityFailure();
        }
        if (!is_string($partition) || preg_match(self::PARTITION_PATTERN, $partition) !== 1) {
            throw $this->integrityFailure();
        }

        return $partition;
    }

    private function assertPartitionBinding(
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        #[\SensitiveParameter]
        string $partitionId,
    ): void {
        if ($this->partitionForTenant($tenant) !== $partitionId) {
            throw $this->integrityFailure();
        }
    }

    private function tenantFromColumns(
        #[\SensitiveParameter]
        mixed $type,
        #[\SensitiveParameter]
        mixed $id,
    ): ?AuditTypedReference {
        if ($type === null && $id === null) {
            return null;
        }
        if (!is_string($type) || !is_string($id)) {
            throw $this->integrityFailure();
        }
        try {
            return new AuditTypedReference($type, $id);
        } catch (\Throwable) {
            throw $this->integrityFailure();
        }
    }

    private function tenantFromValue(#[\SensitiveParameter] mixed $value): ?AuditTypedReference
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
            throw $this->integrityFailure();
        }

        return $this->tenantFromColumns($value['type'], $value['id']);
    }

    private function assertAutoCommit(): void
    {
        try {
            if ($this->connection->isAutoCommit()) {
                return;
            }
        } catch (\Throwable) {
            throw $this->storageFailure();
        }

        throw $this->storageFailure();
    }

    private function assertRecordId(#[\SensitiveParameter] string $recordId): void
    {
        if (preg_match(self::UUID_V7_PATTERN, $recordId) !== 1) {
            throw $this->integrityFailure();
        }
    }

    private function assertExpectedVersion(#[\SensitiveParameter] int $version): void
    {
        if ($version < 1 || $version > self::MAX_SAFE_INTEGER) {
            throw $this->transitionFailure();
        }
    }

    private function assertToken(#[\SensitiveParameter] string $token): void
    {
        if (preg_match(self::UUID_V7_PATTERN, $token) !== 1) {
            throw $this->transitionFailure();
        }
    }

    private function canonicalNow(): string
    {
        try {
            $value = $this->clock
                ->now()
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s.u\Z');
            $this->assertTimestamp($value);
        } catch (\Throwable) {
            throw $this->resourceFailure();
        }

        return $value;
    }

    /** @param array{missing:bool,partitionId:string,tenant:?AuditTypedReference,lastIngressOrdinal:int,lastAssignedAt:?string,stateVersion:int} $state */
    private function assignedAt(
        #[\SensitiveParameter]
        array $state,
        #[\SensitiveParameter]
        string $canonicalNow,
    ): string {
        if ($state['missing'] || $state['lastAssignedAt'] === null) {
            return $canonicalNow;
        }

        return strcmp($canonicalNow, $state['lastAssignedAt']) < 0 ? $state['lastAssignedAt'] : $canonicalNow;
    }

    private function date(#[\SensitiveParameter] string $value): \DateTimeImmutable
    {
        try {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.u\Z', $value, new \DateTimeZone('UTC'));
            $errors = \DateTimeImmutable::getLastErrors();
            if (
                !$date instanceof \DateTimeImmutable
                || is_array($errors) && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0)
                || $date->format('Y-m-d\TH:i:s.u\Z') !== $value
            ) {
                throw new \RuntimeException();
            }

            return $date;
        } catch (\Throwable) {
            throw $this->resourceFailure();
        }
    }

    private function generateUuid(#[\SensitiveParameter] \DateTimeImmutable $time): string
    {
        try {
            $value = $this->uuids->generate($time);
        } catch (\Throwable) {
            throw $this->resourceFailure();
        }
        if (!is_string($value) || preg_match(self::UUID_V7_PATTERN, $value) !== 1) {
            throw $this->resourceFailure();
        }

        return $value;
    }

    private function assertTimestamp(#[\SensitiveParameter] string $value): void
    {
        try {
            AuditRecordTime::assertTimestamp($value);
        } catch (\Throwable) {
            throw $this->integrityFailure();
        }
    }

    private function integer(
        #[\SensitiveParameter]
        mixed $value,
        #[\SensitiveParameter]
        int $minimum,
        #[\SensitiveParameter]
        int $maximum,
    ): int {
        if (is_int($value)) {
            if ($value < $minimum || $value > $maximum) {
                throw $this->integrityFailure();
            }

            return $value;
        }
        if (!is_string($value) || preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value) !== 1) {
            throw $this->integrityFailure();
        }
        $maximumText = (string) $maximum;
        if (
            strlen($value) > strlen($maximumText)
            || strlen($value) === strlen($maximumText) && strcmp($value, $maximumText) > 0
        ) {
            throw $this->integrityFailure();
        }
        $integer = (int) $value;
        if ($integer < $minimum) {
            throw $this->integrityFailure();
        }

        return $integer;
    }

    private function string(#[\SensitiveParameter] mixed $value): string
    {
        if (!is_string($value)) {
            throw $this->integrityFailure();
        }

        return $value;
    }

    private function storageFailure(): AuditOutboxStoreException
    {
        return new AuditOutboxStoreException(AuditOutboxStoreFailure::StorageUnavailable);
    }

    private function integrityFailure(): AuditOutboxStoreException
    {
        return new AuditOutboxStoreException(AuditOutboxStoreFailure::IntegrityInvalid);
    }

    private function resourceFailure(): AuditOutboxStoreException
    {
        return new AuditOutboxStoreException(AuditOutboxStoreFailure::ResourceUnavailable);
    }

    private function transitionFailure(): AuditOutboxStoreException
    {
        return new AuditOutboxStoreException(AuditOutboxStoreFailure::TransitionConflict);
    }

    private function idempotencyFailure(): AuditOutboxStoreException
    {
        return new AuditOutboxStoreException(AuditOutboxStoreFailure::IdempotencyConflict);
    }
}
