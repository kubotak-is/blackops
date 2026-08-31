<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Storage;

use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditRecordDraft;
use BlackOps\Core\Audit\AuditRecordTime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectionException;
use BlackOps\Internal\Audit\Protection\AuditStoredRecord;
use BlackOps\Internal\Audit\Record\AuditChainPosition;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Record\AuditRecordCodec;
use BlackOps\Internal\Audit\Record\AuditRecordFinalizer;
use BlackOps\Internal\Audit\Record\AuditRecordStamp;
use BlackOps\Internal\Audit\Record\AuditRecordValidator;
use BlackOps\Internal\Audit\Record\CanonicalAuditLogicalRecord;
use BlackOps\Internal\Audit\Record\CompleteAuditRecord;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;

/**
 * Reads and appends canonical Audit records at the PostgreSQL storage boundary.
 * Reads do not start or join transactions: tenant and partition predicates are
 * applied by PostgreSQL before protected data reaches the codecs.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class PostgreSqlAuditRecordStore
{
    private bool $quarantined = false;

    private const string UUID_V7_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';

    private const string PARTITION_PATTERN = '/^(?:system:global|tenant:[a-f0-9]{64})$/D';

    /** @var list<string> */
    private const array RECORD_COLUMNS = [
        'schema_version',
        'kind',
        'record_id',
        'partition_id',
        'tenant_type',
        'tenant_id',
        'segment_id',
        'sequence',
        'previous_record_hash',
        'content_hash',
        'record_hash',
        'recorded_at',
        'event_category',
        'protected_body',
        'action_type',
        'resource_type',
        'resource_id',
    ];

    /** @var list<string> */
    private const array HYDRATOR_COLUMNS = [
        'schema_version',
        'kind',
        'record_id',
        'partition_id',
        'tenant_type',
        'tenant_id',
        'segment_id',
        'sequence',
        'previous_record_hash',
        'content_hash',
        'record_hash',
        'recorded_at',
        'event_category',
        'protected_body',
    ];

    /** @var list<string> */
    private const array STATE_COLUMNS = [
        'partition_id',
        'tenant_type',
        'tenant_id',
        'active_segment_id',
        'last_sequence',
        'head_record_hash',
        'last_assigned_recorded_at',
        'state_version',
    ];

    /** @var list<string> */
    private const array SEGMENT_COLUMNS = [
        'partition_id',
        'segment_id',
        'state',
        'first_sequence',
        'final_sequence',
        'head_record_hash',
        'predecessor_record_hash',
        'opened_at',
        'closed_at',
        'successor_segment_id',
        'close_fencing_token',
        'state_version',
    ];

    /** @var list<string> */
    private const array BOUNDARY_COLUMNS = [
        'segment_id',
        'sequence',
        'previous_record_hash',
        'record_hash',
    ];

    /** @var list<string> */
    private const array ORPHAN_COLUMNS = ['segment_exists', 'record_exists'];

    /** @mago-expect lint:excessive-parameter-list */
    public function __construct(
        private Connection $connection,
        private PostgreSqlAuditSchema $schema,
        private ClockInterface $clock,
        private Uuidv7Generator $uuids,
        private AuditRecordValidator $validator,
        private AuditRecordFinalizer $finalizer,
        private AuditPartitionIdDeriver $partitions,
        private AuditRecordCodec $records,
        private AuditProtectedBodyCodec $protection,
        private PostgreSqlAuditStoredRecordHydrator $hydrator,
    ) {}

    /**
     * This operation keeps validation, locking, writing, and owned-transaction
     * cleanup in one failure-safe boundary.
     *
     * @mago-expect lint:halstead
     */
    public function appendRequired(#[\SensitiveParameter] AuditRecordDraft $draft): AuditAppendResult
    {
        $this->assertAutoCommit();
        $partitionId = $this->validateRequiredDraft($draft);
        $ownsRoot = $this->enterTransaction();

        try {
            $this->lockPartition($partitionId);
            $state = $this->readState($partitionId, $draft->tenant);
            $boundary = $this->readAppendBoundary($state, $partitionId, $draft->tenant);
            $this->assertAppendCapacity($state, $boundary);

            $canonicalNow = $this->canonicalNow();
            $assignedAt = $this->assignedAt($state, $canonicalNow);
            $assignedDate = $this->date($assignedAt);
            $segmentId = $state['missing'] ? $this->generateUuid($assignedDate) : $state['activeSegmentId'];
            if (!is_string($segmentId)) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }
            $recordId = $this->generateUuid($assignedDate);
            if ($state['missing']) {
                $this->assertSegmentAvailable($segmentId);
            }
            $this->lockRecord($recordId);
            $this->assertRecordAvailable($recordId);

            $stamp = new AuditRecordStamp($recordId, $assignedAt);
            $logical = $this->finalizeRequired($draft, $stamp);
            $sequence = $state['lastSequence'] + 1;
            $previousHash = $state['headRecordHash'];
            $complete = $this->records->complete(
                $logical,
                new AuditChainPosition($partitionId, $segmentId, $sequence, $previousHash),
            );
            $stored = $this->sealComplete($complete);
            $row = $this->storedRow($stored, $complete);
            $this->writeRequired($state, $boundary, $row, $assignedAt);
            $result = new AuditAppendResult($stored, true);

            if ($ownsRoot) {
                try {
                    $this->connection->commit();
                } catch (\Throwable) {
                    $ownsRoot = false;
                    $this->rollbackOwned();
                    $this->quarantine();
                    throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
                }
            }

            return $result;
        } catch (\Throwable $failure) {
            $safe = $this->safeFailure($failure);
            if ($ownsRoot) {
                try {
                    $this->connection->rollBack();
                } catch (\Throwable) {
                    $this->quarantine();
                    throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
                }
            }

            throw $safe;
        }
    }

    /**
     * This operation keeps idempotent lookup, durable writing, and transaction
     * cleanup together so every failure follows the same safe boundary.
     *
     * @mago-expect lint:halstead
     * @mago-expect lint:no-else-clause
     */
    public function appendDurable(#[\SensitiveParameter] CanonicalAuditLogicalRecord $logical): AuditAppendResult
    {
        $this->assertAutoCommit();
        $validated = $this->validateDurableLogical($logical);
        $partitionId = $validated['partitionId'];
        $recordId = $validated['recordId'];
        $tenant = $validated['tenant'];
        $recordedAt = $validated['recordedAt'];
        $contentHash = $validated['contentHash'];
        $ownsRoot = $this->enterTransaction();

        try {
            $this->lockPartition($partitionId);
            $state = $this->readState($partitionId, $tenant);
            $boundary = $this->readAppendBoundary($state, $partitionId, $tenant);
            $this->lockRecord($recordId);
            $identity = $this->lookupRecordIdentity($recordId);
            if ($identity !== null) {
                if ($identity['partitionId'] !== $partitionId) {
                    throw new AuditStoreException(AuditStoreFailure::IdempotencyConflict);
                }
                $row = $this->lookupSameRecord($recordId, $partitionId);
                try {
                    [$stored, $complete] = $this->verifiedStored($row, $tenant, $partitionId);
                } catch (\Throwable) {
                    throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
                }
                if ($complete->integrity()->contentHash !== $contentHash) {
                    throw new AuditStoreException(AuditStoreFailure::IdempotencyConflict);
                }
                $result = new AuditAppendResult($stored, false);
            } else {
                $this->assertAppendCapacity($state, $boundary);
                $assignedAt = $this->assignedAt($state, $recordedAt);
                $segmentId = $state['missing']
                    ? $this->generateUuid($this->date($recordedAt))
                    : $state['activeSegmentId'];
                if (!is_string($segmentId)) {
                    throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
                }
                if ($state['missing']) {
                    $this->assertSegmentAvailable($segmentId);
                }
                $sequence = $state['lastSequence'] + 1;
                $complete = $this->records->complete(
                    $logical,
                    new AuditChainPosition($partitionId, $segmentId, $sequence, $state['headRecordHash']),
                );
                $stored = $this->sealComplete($complete);
                $row = $this->storedRow($stored, $complete);
                $this->writeRequired($state, $boundary, $row, $assignedAt);
                $result = new AuditAppendResult($stored, true);
            }

            if ($ownsRoot) {
                try {
                    $this->connection->commit();
                } catch (\Throwable) {
                    $ownsRoot = false;
                    $this->rollbackOwned();
                    $this->quarantine();
                    throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
                }
            }

            return $result;
        } catch (\Throwable $failure) {
            $safe = $this->safeFailure($failure);
            if ($ownsRoot) {
                try {
                    $this->connection->rollBack();
                } catch (\Throwable) {
                    $this->quarantine();
                    throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
                }
            }

            throw $safe;
        }
    }

    public function find(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
    ): AuditLookupResult {
        $this->assertAutoCommit();
        $this->assertRecordId($recordId);
        $this->assertPartitionBinding($expectedTenant, $expectedPartitionId);

        try {
            $rows = $this->connection->fetchAllAssociative(
                $this->findSql($expectedTenant),
                $this->findParameters($recordId, $expectedTenant, $expectedPartitionId),
            );
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
        }

        if (!is_array($rows)) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        if ($rows === []) {
            return AuditLookupResult::notFound();
        }
        if (!array_is_list($rows) || count($rows) !== 1 || !is_array($rows[0])) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        /** @var mixed $row */
        $row = $rows[0];
        if (!is_array($row) || array_is_list($row) || array_keys($row) !== self::RECORD_COLUMNS) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        /** @var array{schema_version:mixed,kind:mixed,record_id:mixed,partition_id:mixed,tenant_type:mixed,tenant_id:mixed,segment_id:mixed,sequence:mixed,previous_record_hash:mixed,content_hash:mixed,record_hash:mixed,recorded_at:mixed,event_category:mixed,protected_body:mixed,action_type:mixed,resource_type:mixed,resource_id:mixed} $row */

        try {
            [, $complete] = $this->verifiedStored($row, $expectedTenant, $expectedPartitionId);
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return AuditLookupResult::fromRecord($complete);
    }

    /**
     * This operation validates the active and expected segments, applies the
     * fenced close, and completes owned-transaction cleanup as one boundary.
     *
     * @mago-expect lint:halstead
     * @mago-expect lint:no-else-clause
     */
    public function closeSegment(
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $expectedSegmentId,
    ): AuditSegmentCloseResult {
        $this->assertAutoCommit();
        $this->assertCloseInput($partitionId, $expectedSegmentId);
        $ownsRoot = $this->enterTransaction();

        try {
            $this->lockPartition($partitionId);
            $state = $this->readCloseState($partitionId);
            if ($state['missing']) {
                throw new AuditStoreException(AuditStoreFailure::SegmentConflict);
            }
            $current = $this->readSegment($partitionId, $state['activeSegmentId']);
            $this->assertCurrentSegmentRows($state, $current, $partitionId);
            $segment = $current['segmentId'] === $expectedSegmentId
                ? $current
                : $this->readSegment($partitionId, $expectedSegmentId, true);
            if ($segment['state'] !== 'open') {
                $result = $this->closedSegmentResult($state, $segment, $current);
            } else {
                $this->assertOpenSegment($segment);
                if ($state['activeSegmentId'] !== $expectedSegmentId) {
                    throw new AuditStoreException(AuditStoreFailure::SegmentConflict);
                }
                if ($state['lastSequence'] === 0) {
                    throw new AuditStoreException(AuditStoreFailure::SegmentConflict);
                }
                if ($segment['finalSequence'] === null) {
                    throw new AuditStoreException(AuditStoreFailure::SegmentConflict);
                }
                if ($state['lastSequence'] >= AuditValidationException::MAX_SAFE_INTEGER) {
                    throw new AuditStoreException(AuditStoreFailure::ResourceUnavailable);
                }
                if (
                    $segment['stateVersion'] >= AuditValidationException::MAX_SAFE_INTEGER
                    || $state['stateVersion'] >= AuditValidationException::MAX_SAFE_INTEGER
                ) {
                    throw new AuditStoreException(AuditStoreFailure::ResourceUnavailable);
                }
                $headHash = $state['headRecordHash'];
                if ($headHash === null) {
                    throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
                }

                $closedAt = $this->closeAt($segment['openedAt'], $state['lastAssignedRecordedAt']);
                $successorSegmentId = $this->generateUuid($this->date($closedAt));
                $this->assertSegmentAvailable($successorSegmentId);
                $token = $this->closeToken(
                    $partitionId,
                    $expectedSegmentId,
                    $state['lastSequence'],
                    $headHash,
                    $successorSegmentId,
                    $closedAt,
                );
                $this->writeClose($state, $segment, $successorSegmentId, $closedAt, $token);
                $result = new AuditSegmentCloseResult(
                    $partitionId,
                    $expectedSegmentId,
                    $successorSegmentId,
                    $segment['firstSequence'],
                    $state['lastSequence'],
                    $segment['predecessorRecordHash'],
                    $headHash,
                    $token,
                    $closedAt,
                    true,
                );
            }

            if ($ownsRoot) {
                try {
                    $this->connection->commit();
                } catch (\Throwable) {
                    $ownsRoot = false;
                    $this->rollbackOwned();
                    $this->quarantine();
                    throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
                }
            }

            return $result;
        } catch (\Throwable $failure) {
            $safe = $this->safeFailure($failure);
            if ($ownsRoot && !$this->rollbackOwned()) {
                throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
            }

            throw $safe;
        }
    }

    private function validateRequiredDraft(#[\SensitiveParameter] AuditRecordDraft $draft): string
    {
        try {
            $this->validator->validateDraft($draft);
            if ($draft->policy->auditDelivery !== AuditDelivery::Required) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }

            return $this->partitionForTenant($draft->tenant);
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure, AuditStoreFailure::IntegrityInvalid);
        }
    }

    /** @return array{partitionId:string,recordId:string,tenant:?AuditTypedReference,recordedAt:string,contentHash:string} */
    private function validateDurableLogical(#[\SensitiveParameter] CanonicalAuditLogicalRecord $logical): array
    {
        try {
            if ($logical->definition()->delivery !== AuditDelivery::Durable) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }
            $snapshot = $logical->toArray();
            if (($snapshot['policy']['auditDelivery'] ?? null) !== AuditDelivery::Durable->value) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }
            $tenant = $this->durableTenant($snapshot);
            $recordId = $snapshot['recordId'] ?? null;
            $time = $snapshot['time'] ?? null;
            $recordedAt = is_array($time) ? $time['recordedAt'] ?? null : null;
            if (!is_string($recordId) || !is_string($recordedAt)) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }
            $this->assertRecordId($recordId);
            AuditRecordTime::assertTimestamp($recordedAt);
            $contentHash = $this->records->contentHash($logical);
            if (preg_match('/^[a-f0-9]{64}$/D', $contentHash) !== 1) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }

            return [
                'partitionId' => $this->partitionForTenant($tenant),
                'recordId' => $recordId,
                'tenant' => $tenant,
                'recordedAt' => $recordedAt,
                'contentHash' => $contentHash,
            ];
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure, AuditStoreFailure::IntegrityInvalid);
        }
    }

    /** @param array<string,mixed> $snapshot */
    private function durableTenant(#[\SensitiveParameter] array $snapshot): ?AuditTypedReference
    {
        $tenant = $snapshot['tenant'] ?? null;
        if ($tenant === null) {
            return null;
        }
        if (
            !is_array($tenant)
            || array_is_list($tenant)
            || array_keys($tenant) !== ['type', 'id']
            || !is_string($tenant['type'])
            || !is_string($tenant['id'])
        ) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        try {
            return new AuditTypedReference($tenant['type'], $tenant['id']);
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure, AuditStoreFailure::IntegrityInvalid);
        }
    }

    /** @return array{recordId:string,partitionId:string}|null */
    private function lookupRecordIdentity(#[\SensitiveParameter] string $recordId): ?array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                "SELECT record_id::text AS record_id, partition_id
                    FROM {$this->schema->recordsTable()}
                    WHERE record_id = CAST(:record_id AS uuid)",
                ['record_id' => $recordId],
                ['record_id' => ParameterType::STRING],
            );
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
        if (!is_array($rows)) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        if ($rows === []) {
            return null;
        }
        if (!array_is_list($rows) || count($rows) !== 1 || !is_array($rows[0])) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        /** @var mixed $row */
        $row = $rows[0];
        if (!is_array($row) || array_is_list($row) || array_keys($row) !== ['record_id', 'partition_id']) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        $rowRecordId = $this->string($row['record_id']);
        $this->assertRecordId($rowRecordId);
        if ($rowRecordId !== $recordId) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        $rowPartition = $this->string($row['partition_id']);
        if (preg_match(self::PARTITION_PATTERN, $rowPartition) !== 1) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return ['recordId' => $rowRecordId, 'partitionId' => $rowPartition];
    }

    /**
     * @return array{schema_version:mixed,kind:mixed,record_id:mixed,partition_id:mixed,tenant_type:mixed,tenant_id:mixed,segment_id:mixed,sequence:mixed,previous_record_hash:mixed,content_hash:mixed,record_hash:mixed,recorded_at:mixed,event_category:mixed,protected_body:mixed,action_type:mixed,resource_type:mixed,resource_id:mixed}
     */
    private function lookupSameRecord(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        string $partitionId,
    ): array {
        try {
            $rows = $this->connection->fetchAllAssociative(
                "SELECT schema_version,
                        kind,
                        record_id::text AS record_id,
                        partition_id,
                        tenant_type,
                        tenant_id,
                        segment_id::text AS segment_id,
                        sequence,
                        previous_record_hash,
                        content_hash,
                        record_hash,
                        to_char(recorded_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') AS recorded_at,
                        event_category,
                        protected_body,
                        action_type,
                        resource_type,
                        resource_id
                    FROM {$this->schema->recordsTable()}
                    WHERE record_id = CAST(:record_id AS uuid)
                      AND partition_id = :partition_id",
                ['record_id' => $recordId, 'partition_id' => $partitionId],
                ['record_id' => ParameterType::STRING, 'partition_id' => ParameterType::STRING],
            );
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
        if (!is_array($rows) || !array_is_list($rows) || count($rows) !== 1 || !is_array($rows[0])) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        /** @var mixed $row */
        $row = $rows[0];
        if (!is_array($row) || array_is_list($row) || array_keys($row) !== self::RECORD_COLUMNS) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        /** @var array{schema_version:mixed,kind:mixed,record_id:mixed,partition_id:mixed,tenant_type:mixed,tenant_id:mixed,segment_id:mixed,sequence:mixed,previous_record_hash:mixed,content_hash:mixed,record_hash:mixed,recorded_at:mixed,event_category:mixed,protected_body:mixed,action_type:mixed,resource_type:mixed,resource_id:mixed} $row */

        return $row;
    }

    /**
     * @param array{schema_version:mixed,kind:mixed,record_id:mixed,partition_id:mixed,tenant_type:mixed,tenant_id:mixed,segment_id:mixed,sequence:mixed,previous_record_hash:mixed,content_hash:mixed,record_hash:mixed,recorded_at:mixed,event_category:mixed,protected_body:mixed,action_type:mixed,resource_type:mixed,resource_id:mixed} $row
     * @return array{0:AuditStoredRecord,1:CompleteAuditRecord}
     */
    private function verifiedStored(
        #[\SensitiveParameter]
        array $row,
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        #[\SensitiveParameter]
        string $partitionId,
    ): array {
        $stored = $this->hydrator->hydrate($this->hydratorRow($row));
        $complete = $this->protection->unprotectRecord($stored, $tenant, $partitionId);
        $this->assertQueryIndexes($complete->logicalSnapshot(), $row);

        return [$stored, $complete];
    }

    private function sealComplete(#[\SensitiveParameter] CompleteAuditRecord $complete): AuditStoredRecord
    {
        return $this->protection->protectRecord($complete);
    }

    private function partitionForTenant(#[\SensitiveParameter] ?AuditTypedReference $tenant): string
    {
        try {
            $partitionId = $this->partitions->derive($tenant);
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        if (preg_match(self::PARTITION_PATTERN, $partitionId) !== 1) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return $partitionId;
    }

    private function enterTransaction(): bool
    {
        $this->quarantined = false;
        try {
            $active = $this->connection->isTransactionActive();
            $level = $this->connection->getTransactionNestingLevel();
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
        }
        if (!$active && $level === 0) {
            try {
                $this->connection->beginTransaction();
            } catch (\Throwable) {
                $this->quarantine();
                throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
            }

            return true;
        }
        if ($active && $level === 1) {
            return false;
        }

        throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
    }

    private function lockPartition(#[\SensitiveParameter] string $partitionId): void
    {
        try {
            $this->connection->fetchOne(
                'SELECT pg_advisory_xact_lock(hashtextextended(:lock_key, 0))',
                ['lock_key' => 'blackops.audit.partition.v1:' . $this->schema->schemaName() . ':' . $partitionId],
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
                ['lock_key' => 'blackops.audit.record.v1:' . $this->schema->schemaName() . ':' . $recordId],
                ['lock_key' => ParameterType::STRING],
            );
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
    }

    /**
     * @return array{missing:true,partitionId:string,tenantType:?string,tenantId:?string,activeSegmentId:null,lastSequence:int,headRecordHash:?string,lastAssignedRecordedAt:null,stateVersion:int,segment:null}|array{missing:false,partitionId:string,tenantType:?string,tenantId:?string,activeSegmentId:string,lastSequence:int,headRecordHash:?string,lastAssignedRecordedAt:string,stateVersion:int,segment:null}
     */
    private function readState(
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        bool $allowPersistedTenant = false,
    ): array {
        try {
            $row = $this->connection->fetchAssociative(
                "SELECT partition_id,
                        tenant_type,
                        tenant_id,
                        active_segment_id::text AS active_segment_id,
                        last_sequence,
                        head_record_hash,
                        to_char(last_assigned_recorded_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') AS last_assigned_recorded_at,
                        state_version
                    FROM {$this->schema->partitionStateTable()}
                    WHERE partition_id = :partition_id
                    FOR UPDATE",
                ['partition_id' => $partitionId],
                ['partition_id' => ParameterType::STRING],
            );
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
        if ($row === false) {
            return $this->readMissingState($partitionId);
        }
        if (!is_array($row)) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return $this->stateValues($row, $partitionId, $tenant, $allowPersistedTenant);
    }

    /**
     * @return array{missing:true,partitionId:string,tenantType:?string,tenantId:?string,activeSegmentId:null,lastSequence:int,headRecordHash:?string,lastAssignedRecordedAt:null,stateVersion:int,segment:null}|array{missing:false,partitionId:string,tenantType:?string,tenantId:?string,activeSegmentId:string,lastSequence:int,headRecordHash:?string,lastAssignedRecordedAt:string,stateVersion:int,segment:null}
     */
    private function readCloseState(#[\SensitiveParameter] string $partitionId): array
    {
        return $this->readState($partitionId, null, true);
    }

    /**
     * @return array{missing:true,partitionId:string,tenantType:?string,tenantId:?string,activeSegmentId:null,lastSequence:int,headRecordHash:?string,lastAssignedRecordedAt:null,stateVersion:int,segment:null}
     */
    private function readMissingState(#[\SensitiveParameter] string $partitionId): array
    {
        try {
            $row = $this->connection->fetchAssociative(
                "SELECT CASE WHEN EXISTS (
                            SELECT 1 FROM {$this->schema->segmentsTable()} WHERE partition_id = :partition_id
                        ) THEN 1 ELSE 0 END AS segment_exists,
                        CASE WHEN EXISTS (
                            SELECT 1 FROM {$this->schema->recordsTable()} WHERE partition_id = :partition_id
                        ) THEN 1 ELSE 0 END AS record_exists",
                ['partition_id' => $partitionId],
                ['partition_id' => ParameterType::STRING],
            );
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
        if (!is_array($row) || array_keys($row) !== self::ORPHAN_COLUMNS) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        if ($this->integer($row['segment_exists'], 0, 1) !== 0 || $this->integer($row['record_exists'], 0, 1) !== 0) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return [
            'missing' => true,
            'partitionId' => $partitionId,
            'tenantType' => null,
            'tenantId' => null,
            'activeSegmentId' => null,
            'lastSequence' => 0,
            'headRecordHash' => null,
            'lastAssignedRecordedAt' => null,
            'stateVersion' => 0,
            'segment' => null,
        ];
    }

    /**
     * This helper validates system and tenant state rows before any dependent
     * segment work; the flag selects whether persisted tenant fields are accepted.
     *
     * @param array<array-key,mixed> $row
     * @return array{missing:false,partitionId:string,tenantType:?string,tenantId:?string,activeSegmentId:string,lastSequence:int,headRecordHash:?string,lastAssignedRecordedAt:string,stateVersion:int,segment:null}
     * @mago-expect lint:no-boolean-flag-parameter
     * @mago-expect lint:no-else-clause
     */
    private function stateValues(
        #[\SensitiveParameter]
        array $row,
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        bool $allowPersistedTenant = false,
    ): array {
        if (array_keys($row) !== self::STATE_COLUMNS) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        $rowPartition = $this->string($row['partition_id']);
        $this->assertPartitionValue($rowPartition, $partitionId);
        $tenantType = $this->nullableString($row['tenant_type']);
        $tenantId = $this->nullableString($row['tenant_id']);
        if ($allowPersistedTenant) {
            if (($tenantType === null) !== ($tenantId === null)) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }
            if ($tenantType === null && $partitionId !== 'system:global') {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }
            if ($tenantType !== null && $tenantId !== null) {
                try {
                    $persistedTenant = new AuditTypedReference($tenantType, $tenantId);
                } catch (\Throwable $failure) {
                    throw $this->safeFailure($failure, AuditStoreFailure::IntegrityInvalid);
                }
                if ($this->partitionForTenant($persistedTenant) !== $partitionId) {
                    throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
                }
            }
        } else {
            $this->assertTenantColumns($tenantType, $tenantId, $tenant);
        }
        $activeSegmentId = $this->uuid($row['active_segment_id']);
        $lastSequence = $this->integer($row['last_sequence'], 0, AuditValidationException::MAX_SAFE_INTEGER);
        $headRecordHash = $this->nullableHash($row['head_record_hash']);
        $lastAssignedRecordedAt = $this->string($row['last_assigned_recorded_at']);
        AuditRecordTime::assertTimestamp($lastAssignedRecordedAt);
        $stateVersion = $this->integer($row['state_version'], 1, AuditValidationException::MAX_SAFE_INTEGER);
        if ($lastSequence === 0 && $headRecordHash !== null || $lastSequence > 0 && $headRecordHash === null) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return [
            'missing' => false,
            'partitionId' => $partitionId,
            'tenantType' => $tenantType,
            'tenantId' => $tenantId,
            'activeSegmentId' => $activeSegmentId,
            'lastSequence' => $lastSequence,
            'headRecordHash' => $headRecordHash,
            'lastAssignedRecordedAt' => $lastAssignedRecordedAt,
            'stateVersion' => $stateVersion,
            'segment' => null,
        ];
    }

    /**
     * This helper validates every persisted chain shape before a write, including
     * genesis, open, closed, and predecessor boundaries.
     *
     * @param array{missing:true,partitionId:string,tenantType:?string,tenantId:?string,activeSegmentId:null,lastSequence:int,headRecordHash:?string,lastAssignedRecordedAt:null,stateVersion:int,segment:null}|array{missing:false,partitionId:string,tenantType:?string,tenantId:?string,activeSegmentId:string,lastSequence:int,headRecordHash:?string,lastAssignedRecordedAt:string,stateVersion:int,segment:null} $state
     * @return array{segment:null|array{partitionId:string,segmentId:string,state:'open'|'closed'|'sealed',firstSequence:int,finalSequence:?int,headRecordHash:?string,predecessorRecordHash:?string,openedAt:string,closedAt:?string,successorSegmentId:?string,closeFencingToken:?string,stateVersion:int},endpoint:null|array{segmentId:string,sequence:int,previousRecordHash:?string,recordHash:string},minimum:null|array{segmentId:string,sequence:int,previousRecordHash:?string,recordHash:string},predecessor:null|array{segmentId:string,sequence:int,previousRecordHash:?string,recordHash:string}}
     * @mago-expect lint:halstead
     * @mago-expect lint:no-else-clause
     */
    private function readAppendBoundary(
        #[\SensitiveParameter]
        array $state,
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
    ): array {
        if ($state['missing']) {
            return ['segment' => null, 'endpoint' => null, 'minimum' => null, 'predecessor' => null];
        }
        $segment = $this->readSegment($partitionId, $state['activeSegmentId']);
        $this->assertOpenSegment($segment);
        if ($state['lastSequence'] === 0) {
            if (
                $segment['firstSequence'] !== 1
                || $segment['finalSequence'] !== null
                || $segment['headRecordHash'] !== null
                || $segment['predecessorRecordHash'] !== null
            ) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }

            return ['segment' => $segment, 'endpoint' => null, 'minimum' => null, 'predecessor' => null];
        }

        $endpoint = $this->readBoundary($partitionId, $state['lastSequence']);
        if ($endpoint['sequence'] !== $state['lastSequence'] || $endpoint['recordHash'] !== $state['headRecordHash']) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        if ($segment['finalSequence'] === null) {
            if (
                $segment['firstSequence'] !== ($state['lastSequence'] + 1)
                || $segment['headRecordHash'] !== null
                || $segment['predecessorRecordHash'] !== $state['headRecordHash']
                || $endpoint['segmentId'] === $segment['segmentId']
            ) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }

            return ['segment' => $segment, 'endpoint' => $endpoint, 'minimum' => null, 'predecessor' => $endpoint];
        }
        if (
            $segment['finalSequence'] !== $state['lastSequence']
            || $segment['headRecordHash'] !== $state['headRecordHash']
            || $segment['firstSequence'] > $state['lastSequence']
            || $endpoint['segmentId'] !== $segment['segmentId']
        ) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        $minimum = $this->readMinimum($partitionId, $segment['segmentId']);
        if (
            $minimum['sequence'] !== $segment['firstSequence']
            || $minimum['segmentId'] !== $segment['segmentId']
            || $minimum['previousRecordHash'] !== $segment['predecessorRecordHash']
        ) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        $predecessor = null;
        if ($segment['firstSequence'] === 1) {
            if ($minimum['previousRecordHash'] !== null) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }
        } else {
            $predecessor = $this->readBoundary($partitionId, $segment['firstSequence'] - 1);
            if (
                $predecessor['sequence'] !== ($segment['firstSequence'] - 1)
                || $predecessor['recordHash'] !== $segment['predecessorRecordHash']
                || $predecessor['segmentId'] === $segment['segmentId']
            ) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }
        }

        return ['segment' => $segment, 'endpoint' => $endpoint, 'minimum' => $minimum, 'predecessor' => $predecessor];
    }

    /**
     * This helper maps one segment row and distinguishes a missing row from a
     * conflict while preserving the store's safe failure mapping.
     *
     * @return array{partitionId:string,segmentId:string,state:'open'|'closed'|'sealed',firstSequence:int,finalSequence:?int,headRecordHash:?string,predecessorRecordHash:?string,openedAt:string,closedAt:?string,successorSegmentId:?string,closeFencingToken:?string,stateVersion:int}
     * @mago-expect lint:halstead
     * @mago-expect lint:no-boolean-flag-parameter
     */
    private function readSegment(
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $segmentId,
        bool $missingIsConflict = false,
    ): array {
        try {
            $row = $this->connection->fetchAssociative(
                "SELECT partition_id,
                        segment_id::text AS segment_id,
                        state,
                        first_sequence,
                        final_sequence,
                        head_record_hash,
                        predecessor_record_hash,
                        to_char(opened_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') AS opened_at,
                        CASE WHEN closed_at IS NULL THEN NULL ELSE to_char(closed_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') END AS closed_at,
                        successor_segment_id::text AS successor_segment_id,
                        close_fencing_token,
                        state_version
                    FROM {$this->schema->segmentsTable()}
                    WHERE partition_id = :partition_id AND segment_id = :segment_id
                    FOR UPDATE",
                ['partition_id' => $partitionId, 'segment_id' => $segmentId],
                ['partition_id' => ParameterType::STRING, 'segment_id' => ParameterType::STRING],
            );
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
        if ($row === false && $missingIsConflict) {
            throw new AuditStoreException(AuditStoreFailure::SegmentConflict);
        }
        if (!is_array($row)) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        if (array_keys($row) !== self::SEGMENT_COLUMNS) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        $rowPartition = $this->string($row['partition_id']);
        $this->assertPartitionValue($rowPartition, $partitionId);
        $rowSegment = $this->uuid($row['segment_id']);
        if ($rowSegment !== $segmentId) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        $stateName = $this->string($row['state']);
        if (!in_array($stateName, ['open', 'closed', 'sealed'], strict: true)) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        $firstSequence = $this->integer($row['first_sequence'], 1, AuditValidationException::MAX_SAFE_INTEGER);
        $finalSequence = $row['final_sequence'] === null
            ? null
            : $this->integer($row['final_sequence'], 1, AuditValidationException::MAX_SAFE_INTEGER);
        $headRecordHash = $this->nullableHash($row['head_record_hash']);
        $predecessorRecordHash = $this->nullableHash($row['predecessor_record_hash']);
        $openedAt = $this->string($row['opened_at']);
        AuditRecordTime::assertTimestamp($openedAt);
        $closedAt = $row['closed_at'] === null ? null : $this->string($row['closed_at']);
        if ($closedAt !== null) {
            AuditRecordTime::assertTimestamp($closedAt);
        }
        $successor = $row['successor_segment_id'] === null ? null : $this->uuid($row['successor_segment_id']);
        $token = $row['close_fencing_token'] === null ? null : $this->string($row['close_fencing_token']);
        if ($token !== null && preg_match('/^aft:v1:[a-f0-9]{64}$/D', $token) !== 1) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        $stateVersion = $this->integer($row['state_version'], 1, AuditValidationException::MAX_SAFE_INTEGER);

        return [
            'partitionId' => $partitionId,
            'segmentId' => $rowSegment,
            'state' => $stateName,
            'firstSequence' => $firstSequence,
            'finalSequence' => $finalSequence,
            'headRecordHash' => $headRecordHash,
            'predecessorRecordHash' => $predecessorRecordHash,
            'openedAt' => $openedAt,
            'closedAt' => $closedAt,
            'successorSegmentId' => $successor,
            'closeFencingToken' => $token,
            'stateVersion' => $stateVersion,
        ];
    }

    /** @param array{partitionId:string,segmentId:string,state:'open'|'closed'|'sealed',firstSequence:int,finalSequence:?int,headRecordHash:?string,predecessorRecordHash:?string,openedAt:string,closedAt:?string,successorSegmentId:?string,closeFencingToken:?string,stateVersion:int} $segment */
    private function assertOpenSegment(#[\SensitiveParameter] array $segment): void
    {
        if (
            $segment['state'] !== 'open'
            || $segment['closedAt'] !== null
            || $segment['successorSegmentId'] !== null
            || $segment['closeFencingToken'] !== null
            || $segment['finalSequence'] !== null && $segment['headRecordHash'] === null
            || $segment['finalSequence'] === null && $segment['headRecordHash'] !== null
        ) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }

    /** @return array{segmentId:string,sequence:int,previousRecordHash:?string,recordHash:string} */
    private function readBoundary(
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        int $sequence,
    ): array {
        try {
            $rows = $this->connection->fetchAllAssociative(
                "SELECT segment_id::text AS segment_id,
                        sequence,
                        previous_record_hash,
                        record_hash
                    FROM {$this->schema->recordsTable()}
                    WHERE partition_id = :partition_id AND sequence = :sequence",
                ['partition_id' => $partitionId, 'sequence' => $sequence],
                ['partition_id' => ParameterType::STRING, 'sequence' => ParameterType::INTEGER],
            );
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
        if (!is_array($rows) || !array_is_list($rows) || count($rows) !== 1 || !is_array($rows[0])) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        $row = $rows[0];
        if (array_keys($row) !== self::BOUNDARY_COLUMNS) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        $rowSegment = $this->uuid($row['segment_id']);
        $rowSequence = $this->integer($row['sequence'], 1, AuditValidationException::MAX_SAFE_INTEGER);
        $previous = $this->nullableHash($row['previous_record_hash']);
        $recordHash = $this->hash($row['record_hash']);

        return [
            'segmentId' => $rowSegment,
            'sequence' => $rowSequence,
            'previousRecordHash' => $previous,
            'recordHash' => $recordHash,
        ];
    }

    /** @return array{segmentId:string,sequence:int,previousRecordHash:?string,recordHash:string} */
    private function readMinimum(
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $segmentId,
    ): array {
        try {
            $rows = $this->connection->fetchAllAssociative(
                "SELECT segment_id::text AS segment_id,
                        sequence,
                        previous_record_hash,
                        record_hash
                    FROM {$this->schema->recordsTable()}
                    WHERE partition_id = :partition_id AND segment_id = :segment_id
                    ORDER BY sequence ASC
                    LIMIT 1",
                ['partition_id' => $partitionId, 'segment_id' => $segmentId],
                ['partition_id' => ParameterType::STRING, 'segment_id' => ParameterType::STRING],
            );
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
        if (!is_array($rows) || !array_is_list($rows) || count($rows) !== 1 || !is_array($rows[0])) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        $row = $rows[0];
        if (array_keys($row) !== self::BOUNDARY_COLUMNS) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return [
            'segmentId' => $this->uuid($row['segment_id']),
            'sequence' => $this->integer($row['sequence'], 1, AuditValidationException::MAX_SAFE_INTEGER),
            'previousRecordHash' => $this->nullableHash($row['previous_record_hash']),
            'recordHash' => $this->hash($row['record_hash']),
        ];
    }

    private function assertCloseInput(
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $expectedSegmentId,
    ): void {
        if (preg_match(self::PARTITION_PATTERN, $partitionId) !== 1) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        $this->assertRecordId($expectedSegmentId);
    }

    /**
     * This helper verifies the closed segment, its successor chain, and fencing
     * tokens before constructing a retry result.
     *
     * @param array{missing:true,partitionId:string,tenantType:?string,tenantId:?string,activeSegmentId:null,lastSequence:int,headRecordHash:?string,lastAssignedRecordedAt:null,stateVersion:int,segment:null}|array{missing:false,partitionId:string,tenantType:?string,tenantId:?string,activeSegmentId:string,lastSequence:int,headRecordHash:?string,lastAssignedRecordedAt:string,stateVersion:int,segment:null} $state
     * @param array{partitionId:string,segmentId:string,state:'open'|'closed'|'sealed',firstSequence:int,finalSequence:?int,headRecordHash:?string,predecessorRecordHash:?string,openedAt:string,closedAt:?string,successorSegmentId:?string,closeFencingToken:?string,stateVersion:int} $segment
     * @param array{partitionId:string,segmentId:string,state:'open'|'closed'|'sealed',firstSequence:int,finalSequence:?int,headRecordHash:?string,predecessorRecordHash:?string,openedAt:string,closedAt:?string,successorSegmentId:?string,closeFencingToken:?string,stateVersion:int} $current
     * @mago-expect lint:halstead
     * @mago-expect lint:no-else-clause
     */
    private function closedSegmentResult(
        #[\SensitiveParameter]
        array $state,
        #[\SensitiveParameter]
        array $segment,
        #[\SensitiveParameter]
        array $current,
    ): AuditSegmentCloseResult {
        $segmentFinalSequence = $segment['finalSequence'];
        $segmentHeadHash = $segment['headRecordHash'];
        $segmentClosedAt = $segment['closedAt'];
        $segmentSuccessorId = $segment['successorSegmentId'];
        $segmentToken = $segment['closeFencingToken'];
        if (
            $state['missing']
            || $segmentFinalSequence === null
            || $segmentHeadHash === null
            || $segmentClosedAt === null
            || $segmentSuccessorId === null
            || $segmentToken === null
            || $segmentFinalSequence < $segment['firstSequence']
            || $segment['stateVersion'] < 2
            || strcmp($segmentClosedAt, $segment['openedAt']) < 0
        ) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        $successor = $current['segmentId'] === $segmentSuccessorId
            ? $current
            : $this->readSegment($state['partitionId'], $segmentSuccessorId);
        $successorFinalSequence = $successor['finalSequence'];
        $successorHeadHash = $successor['headRecordHash'];
        $successorClosedAt = $successor['closedAt'];
        $successorSuccessorId = $successor['successorSegmentId'];
        $successorToken = $successor['closeFencingToken'];
        if (
            $successor['firstSequence'] !== ($segmentFinalSequence + 1)
            || $successor['predecessorRecordHash'] !== $segmentHeadHash
            || $successor['openedAt'] !== $segmentClosedAt
            || $successor['segmentId'] === $segment['segmentId']
            || $successor['successorSegmentId'] === $successor['segmentId']
        ) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        if ($successor['state'] === 'open') {
            if ($state['activeSegmentId'] !== $successor['segmentId']) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }
            $this->assertOpenSegment($successor);
        } elseif (
            $successorFinalSequence === null
            || $successorHeadHash === null
            || $successorClosedAt === null
            || $successorSuccessorId === null
            || $successorToken === null
            || $successorFinalSequence < $successor['firstSequence']
            || $successor['stateVersion'] < 2
            || strcmp($successorClosedAt, $successor['openedAt']) < 0
        ) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        if ($successor['state'] !== 'open') {
            if (
                $successorFinalSequence === null
                || $successorHeadHash === null
                || $successorClosedAt === null
                || $successorSuccessorId === null
                || $successorToken === null
            ) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }
            $this->assertClosedRows($state['partitionId'], $successor);
            if (!hash_equals($successorToken, $this->closeToken(
                $state['partitionId'],
                $successor['segmentId'],
                $successorFinalSequence,
                $successorHeadHash,
                $successorSuccessorId,
                $successorClosedAt,
            ))) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }
        }
        $this->assertClosedRows($state['partitionId'], $segment);
        if (!hash_equals($segmentToken, $this->closeToken(
            $state['partitionId'],
            $segment['segmentId'],
            $segmentFinalSequence,
            $segmentHeadHash,
            $successor['segmentId'],
            $segmentClosedAt,
        ))) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return new AuditSegmentCloseResult(
            $state['partitionId'],
            $segment['segmentId'],
            $successor['segmentId'],
            $segment['firstSequence'],
            $segmentFinalSequence,
            $segment['predecessorRecordHash'],
            $segmentHeadHash,
            $segmentToken,
            $segmentClosedAt,
            false,
        );
    }

    /**
     * This helper validates current chain endpoints, segment minima, and
     * predecessor invariants before append or close work proceeds.
     *
     * @param array{missing:true,partitionId:string,tenantType:?string,tenantId:?string,activeSegmentId:null,lastSequence:int,headRecordHash:?string,lastAssignedRecordedAt:null,stateVersion:int,segment:null}|array{missing:false,partitionId:string,tenantType:?string,tenantId:?string,activeSegmentId:string,lastSequence:int,headRecordHash:?string,lastAssignedRecordedAt:string,stateVersion:int,segment:null} $state
     * @param array{partitionId:string,segmentId:string,state:'open'|'closed'|'sealed',firstSequence:int,finalSequence:?int,headRecordHash:?string,predecessorRecordHash:?string,openedAt:string,closedAt:?string,successorSegmentId:?string,closeFencingToken:?string,stateVersion:int} $segment
     * @mago-expect lint:halstead
     * @mago-expect lint:no-else-clause
     */
    private function assertCurrentSegmentRows(
        #[\SensitiveParameter]
        array $state,
        #[\SensitiveParameter]
        array $segment,
        #[\SensitiveParameter]
        string $partitionId,
    ): void {
        if ($state['missing'] || $segment['segmentId'] !== $state['activeSegmentId']) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        $this->assertOpenSegment($segment);
        if ($state['lastSequence'] === 0) {
            if (
                $segment['firstSequence'] !== 1
                || $segment['finalSequence'] !== null
                || $segment['headRecordHash'] !== null
                || $segment['predecessorRecordHash'] !== null
            ) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }

            return;
        }
        $endpoint = $this->readBoundary($partitionId, $state['lastSequence']);
        if ($endpoint['sequence'] !== $state['lastSequence'] || $endpoint['recordHash'] !== $state['headRecordHash']) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        if ($segment['finalSequence'] === null) {
            if (
                $segment['firstSequence'] !== ($state['lastSequence'] + 1)
                || $segment['headRecordHash'] !== null
                || $segment['predecessorRecordHash'] !== $state['headRecordHash']
                || $endpoint['segmentId'] === $segment['segmentId']
            ) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }

            return;
        }
        if (
            $segment['finalSequence'] !== $state['lastSequence']
            || $segment['headRecordHash'] !== $state['headRecordHash']
            || $segment['firstSequence'] > $state['lastSequence']
            || $endpoint['segmentId'] !== $segment['segmentId']
        ) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        $minimum = $this->readMinimum($partitionId, $segment['segmentId']);
        if (
            $minimum['sequence'] !== $segment['firstSequence']
            || $minimum['segmentId'] !== $segment['segmentId']
            || $minimum['previousRecordHash'] !== $segment['predecessorRecordHash']
        ) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        if ($segment['firstSequence'] > 1) {
            $predecessor = $this->readBoundary($partitionId, $segment['firstSequence'] - 1);
            if (
                $predecessor['sequence'] !== ($segment['firstSequence'] - 1)
                || $predecessor['recordHash'] !== $segment['predecessorRecordHash']
                || $predecessor['segmentId'] === $segment['segmentId']
            ) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }
        } elseif ($segment['predecessorRecordHash'] !== null) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }

    /**
     * @param array{partitionId:string,segmentId:string,state:'open'|'closed'|'sealed',firstSequence:int,finalSequence:?int,headRecordHash:?string,predecessorRecordHash:?string,openedAt:string,closedAt:?string,successorSegmentId:?string,closeFencingToken:?string,stateVersion:int} $segment
     */
    private function assertClosedRows(
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        array $segment,
    ): void {
        $finalSequence = $segment['finalSequence'];
        if ($finalSequence === null) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        $endpoint = $this->readBoundary($partitionId, $finalSequence);
        $minimum = $this->readMinimum($partitionId, $segment['segmentId']);
        if (
            $endpoint['segmentId'] !== $segment['segmentId']
            || $endpoint['sequence'] !== $finalSequence
            || $endpoint['recordHash'] !== $segment['headRecordHash']
            || $minimum['segmentId'] !== $segment['segmentId']
            || $minimum['sequence'] !== $segment['firstSequence']
            || $minimum['previousRecordHash'] !== $segment['predecessorRecordHash']
        ) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        if ($segment['firstSequence'] === 1) {
            if ($segment['predecessorRecordHash'] !== null) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }

            return;
        }
        $predecessor = $this->readBoundary($partitionId, $segment['firstSequence'] - 1);
        if (
            $predecessor['sequence'] !== ($segment['firstSequence'] - 1)
            || $predecessor['recordHash'] !== $segment['predecessorRecordHash']
            || $predecessor['segmentId'] === $segment['segmentId']
        ) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }

    private function closeAt(
        #[\SensitiveParameter]
        string $openedAt,
        #[\SensitiveParameter]
        string $lastAssignedAt,
    ): string {
        $now = $this->canonicalNow();
        $closedAt = strcmp($now, $openedAt) < 0 ? $openedAt : $now;

        return strcmp($closedAt, $lastAssignedAt) < 0 ? $lastAssignedAt : $closedAt;
    }

    /** @mago-expect lint:excessive-parameter-list */
    private function closeToken(
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $closedSegmentId,
        int $finalSequence,
        #[\SensitiveParameter]
        string $headHash,
        #[\SensitiveParameter]
        string $successorSegmentId,
        #[\SensitiveParameter]
        string $closedAt,
    ): string {
        $head = hex2bin($headHash);
        if ($head === false) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        $preimage =
            $this->encodeString('blackops.audit.segment.close.v1')
            . $this->encodeString($partitionId)
            . $this->encodeString($closedSegmentId)
            . $this->encodeInteger($finalSequence)
            . $this->encodeBytes($head)
            . $this->encodeString($successorSegmentId)
            . $this->encodeString($closedAt);

        return 'aft:v1:' . hash('sha256', $preimage);
    }

    private function encodeString(#[\SensitiveParameter] string $value): string
    {
        return 's:' . strlen($value) . ':' . $value;
    }

    private function encodeBytes(#[\SensitiveParameter] string $value): string
    {
        return 'b:' . strlen($value) . ':' . $value;
    }

    private function encodeInteger(#[\SensitiveParameter] int $value): string
    {
        return 'i:' . $value;
    }

    /** @param array<string,mixed> $state @param array<string,mixed> $segment */
    private function writeClose(
        #[\SensitiveParameter]
        array $state,
        #[\SensitiveParameter]
        array $segment,
        #[\SensitiveParameter]
        string $successorSegmentId,
        #[\SensitiveParameter]
        string $closedAt,
        #[\SensitiveParameter]
        string $token,
    ): void {
        $parameters = [
            'partition_id' => $state['partitionId'],
            'closed_segment_id' => $segment['segmentId'],
            'successor_segment_id' => $successorSegmentId,
            'final_sequence' => $state['lastSequence'],
            'head_record_hash' => $state['headRecordHash'],
            'closed_at' => $closedAt,
            'close_fencing_token' => $token,
            'segment_version' => $segment['stateVersion'],
            'state_version' => $state['stateVersion'],
        ];
        $types = [
            'partition_id' => ParameterType::STRING,
            'closed_segment_id' => ParameterType::STRING,
            'successor_segment_id' => ParameterType::STRING,
            'final_sequence' => ParameterType::INTEGER,
            'head_record_hash' => ParameterType::STRING,
            'closed_at' => ParameterType::STRING,
            'close_fencing_token' => ParameterType::STRING,
            'segment_version' => ParameterType::INTEGER,
            'state_version' => ParameterType::INTEGER,
        ];
        try {
            $result = $this->connection->fetchOne($this->closeSql(), $parameters, $types);
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
        if ((string) $result !== '1') {
            throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
        }
    }

    private function closeSql(): string
    {
        $segments = $this->schema->segmentsTable();
        $states = $this->schema->partitionStateTable();

        return "WITH closed_segment AS (
                UPDATE {$segments}
                SET state = 'closed', closed_at = CAST(:closed_at AS timestamptz),
                    successor_segment_id = CAST(:successor_segment_id AS uuid),
                    close_fencing_token = :close_fencing_token,
                    state_version = state_version + 1
                WHERE partition_id = :partition_id
                  AND segment_id = CAST(:closed_segment_id AS uuid)
                  AND state = 'open'
                  AND final_sequence = CAST(:final_sequence AS bigint)
                  AND head_record_hash = :head_record_hash
                  AND state_version = CAST(:segment_version AS bigint)
                RETURNING partition_id
            ),
            successor_segment AS (
                INSERT INTO {$segments} (
                    partition_id, segment_id, state, first_sequence, final_sequence,
                    head_record_hash, predecessor_record_hash, opened_at, closed_at,
                    successor_segment_id, close_fencing_token, state_version
                )
                SELECT :partition_id, CAST(:successor_segment_id AS uuid), 'open',
                       CAST(:final_sequence AS bigint) + 1, NULL, NULL, :head_record_hash,
                       CAST(:closed_at AS timestamptz), NULL, NULL, NULL, 1
                FROM closed_segment
                RETURNING partition_id
            ),
            advanced_state AS (
                UPDATE {$states}
                SET active_segment_id = CAST(:successor_segment_id AS uuid),
                    state_version = state_version + 1
                WHERE partition_id = :partition_id
                  AND active_segment_id = CAST(:closed_segment_id AS uuid)
                  AND last_sequence = CAST(:final_sequence AS bigint)
                  AND head_record_hash = :head_record_hash
                  AND state_version = CAST(:state_version AS bigint)
                  AND EXISTS (SELECT 1 FROM successor_segment)
                RETURNING partition_id
            ),
            fence AS (
                SELECT CAST(CASE WHEN (
                    (SELECT count(*) FROM closed_segment) = 1
                    AND (SELECT count(*) FROM successor_segment) = 1
                    AND (SELECT count(*) FROM advanced_state) = 1
                ) THEN '1' ELSE 'audit_store_abort' END AS bigint) AS committed
            )
            SELECT committed FROM fence";
    }

    /**
     * @param array{missing:bool,partitionId:string,tenantType:?string,tenantId:?string,activeSegmentId:?string,lastSequence:int,headRecordHash:?string,lastAssignedRecordedAt:?string,stateVersion:int,segment:null} $state
     * @param array{segment:null|array{partitionId:string,segmentId:string,state:'open'|'closed'|'sealed',firstSequence:int,finalSequence:?int,headRecordHash:?string,predecessorRecordHash:?string,openedAt:string,closedAt:?string,successorSegmentId:?string,closeFencingToken:?string,stateVersion:int},endpoint:null|array{segmentId:string,sequence:int,previousRecordHash:?string,recordHash:string},minimum:null|array{segmentId:string,sequence:int,previousRecordHash:?string,recordHash:string},predecessor:null|array{segmentId:string,sequence:int,previousRecordHash:?string,recordHash:string}} $boundary
     */
    private function assertAppendCapacity(
        #[\SensitiveParameter]
        array $state,
        #[\SensitiveParameter]
        array $boundary,
    ): void {
        if ($state['lastSequence'] >= AuditValidationException::MAX_SAFE_INTEGER) {
            throw new AuditStoreException(AuditStoreFailure::ResourceUnavailable);
        }
        if (!$state['missing'] && $state['stateVersion'] >= AuditValidationException::MAX_SAFE_INTEGER) {
            throw new AuditStoreException(AuditStoreFailure::ResourceUnavailable);
        }
        if (!$state['missing'] && $boundary['segment']['stateVersion'] >= AuditValidationException::MAX_SAFE_INTEGER) {
            throw new AuditStoreException(AuditStoreFailure::ResourceUnavailable);
        }
    }

    private function canonicalNow(): string
    {
        try {
            $now = $this->clock->now();
            $value = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
            AuditRecordTime::assertTimestamp($value);
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::ResourceUnavailable);
        }

        return $value;
    }

    /**
     * @param array{missing:true,partitionId:string,tenantType:?string,tenantId:?string,activeSegmentId:null,lastSequence:int,headRecordHash:?string,lastAssignedRecordedAt:null,stateVersion:int,segment:null}|array{missing:false,partitionId:string,tenantType:?string,tenantId:?string,activeSegmentId:string,lastSequence:int,headRecordHash:?string,lastAssignedRecordedAt:string,stateVersion:int,segment:null} $state
     */
    private function assignedAt(
        #[\SensitiveParameter]
        array $state,
        #[\SensitiveParameter]
        string $canonicalNow,
    ): string {
        if ($state['missing'] || $state['lastAssignedRecordedAt'] === null) {
            return $canonicalNow;
        }

        return strcmp($canonicalNow, $state['lastAssignedRecordedAt']) < 0
            ? $state['lastAssignedRecordedAt']
            : $canonicalNow;
    }

    private function date(#[\SensitiveParameter] string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.u\Z', $value, new \DateTimeZone('UTC'));
        if (!$date instanceof \DateTimeImmutable) {
            throw new AuditStoreException(AuditStoreFailure::ResourceUnavailable);
        }

        return $date;
    }

    private function generateUuid(#[\SensitiveParameter] \DateTimeImmutable $time): string
    {
        try {
            $uuid = $this->uuids->generate($time);
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::ResourceUnavailable);
        }
        if (!is_string($uuid) || preg_match(self::UUID_V7_PATTERN, $uuid) !== 1) {
            throw new AuditStoreException(AuditStoreFailure::ResourceUnavailable);
        }

        return $uuid;
    }

    private function assertSegmentAvailable(#[\SensitiveParameter] string $segmentId): void
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                "SELECT segment_id::text AS segment_id FROM {$this->schema->segmentsTable()} WHERE segment_id = :segment_id",
                ['segment_id' => $segmentId],
                ['segment_id' => ParameterType::STRING],
            );
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
        if (!is_array($rows)) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        if ($rows !== []) {
            throw new AuditStoreException(AuditStoreFailure::ResourceUnavailable);
        }
    }

    private function assertRecordAvailable(#[\SensitiveParameter] string $recordId): void
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                "SELECT record_id::text AS record_id FROM {$this->schema->recordsTable()} WHERE record_id = :record_id",
                ['record_id' => $recordId],
                ['record_id' => ParameterType::STRING],
            );
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
        if (!is_array($rows)) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        if ($rows !== []) {
            throw new AuditStoreException(AuditStoreFailure::ResourceUnavailable);
        }
    }

    private function finalizeRequired(
        #[\SensitiveParameter]
        AuditRecordDraft $draft,
        #[\SensitiveParameter]
        AuditRecordStamp $stamp,
    ): CanonicalAuditLogicalRecord {
        try {
            $logical = $this->finalizer->finalize($draft, $stamp);
            $snapshot = $logical->toArray();
            if (
                $logical->definition()->delivery !== AuditDelivery::Required
                || ($snapshot['policy']['auditDelivery'] ?? null) !== AuditDelivery::Required->value
            ) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }

            return $logical;
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure, AuditStoreFailure::IntegrityInvalid);
        }
    }

    /**
     * @return array{schema_version:mixed,kind:mixed,record_id:mixed,partition_id:mixed,tenant_type:mixed,tenant_id:mixed,segment_id:mixed,sequence:mixed,previous_record_hash:mixed,content_hash:mixed,record_hash:mixed,recorded_at:mixed,event_category:mixed,protected_body:mixed,action_type:mixed,resource_type:mixed,resource_id:mixed}
     */
    private function storedRow(
        #[\SensitiveParameter]
        AuditStoredRecord $stored,
        #[\SensitiveParameter]
        CompleteAuditRecord $complete,
    ): array {
        $value = $stored->toArray();
        $logical = $complete->logicalSnapshot();
        $tenant = $value['tenantBinding'] ?? null;
        $tenantType = is_array($tenant) ? $tenant['type'] ?? null : null;
        $tenantId = is_array($tenant) ? $tenant['id'] ?? null : null;
        $action = $logical['action'] ?? null;
        $resource = $logical['resource'] ?? null;
        if (!is_array($action) || !is_array($resource)) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return [
            'schema_version' => $value['schemaVersion'] ?? null,
            'kind' => $value['kind'] ?? null,
            'record_id' => $value['recordId'] ?? null,
            'partition_id' => $value['partitionId'] ?? null,
            'tenant_type' => $tenantType,
            'tenant_id' => $tenantId,
            'segment_id' => $value['segmentId'] ?? null,
            'sequence' => $value['sequence'] ?? null,
            'previous_record_hash' => $value['previousRecordHash'] ?? null,
            'content_hash' => $value['contentHash'] ?? null,
            'record_hash' => $value['recordHash'] ?? null,
            'recorded_at' => $value['recordedAt'] ?? null,
            'event_category' => $value['eventCategory'] ?? null,
            'protected_body' => $value['protectedBody'] ?? null,
            'action_type' => $action['type'] ?? null,
            'resource_type' => $resource['type'] ?? null,
            'resource_id' => $resource['id'] ?? null,
        ];
    }

    /**
     * This helper binds the complete required-record CTE and maps a failed row
     * count to the store's fixed storage failure.
     *
     * @param array{missing:bool,partitionId:string,tenantType:?string,tenantId:?string,activeSegmentId:?string,lastSequence:int,headRecordHash:?string,lastAssignedRecordedAt:?string,stateVersion:int,segment:null} $state
     * @param array{segment:null|array{partitionId:string,segmentId:string,state:'open'|'closed'|'sealed',firstSequence:int,finalSequence:?int,headRecordHash:?string,predecessorRecordHash:?string,openedAt:string,closedAt:?string,successorSegmentId:?string,closeFencingToken:?string,stateVersion:int},endpoint:null|array{segmentId:string,sequence:int,previousRecordHash:?string,recordHash:string},minimum:null|array{segmentId:string,sequence:int,previousRecordHash:?string,recordHash:string},predecessor:null|array{segmentId:string,sequence:int,previousRecordHash:?string,recordHash:string}} $boundary
     * @param array{schema_version:mixed,kind:mixed,record_id:mixed,partition_id:mixed,tenant_type:mixed,tenant_id:mixed,segment_id:mixed,sequence:mixed,previous_record_hash:mixed,content_hash:mixed,record_hash:mixed,recorded_at:mixed,event_category:mixed,protected_body:mixed,action_type:mixed,resource_type:mixed,resource_id:mixed} $row
     * @mago-expect lint:halstead
     */
    private function writeRequired(
        #[\SensitiveParameter]
        array $state,
        #[\SensitiveParameter]
        array $boundary,
        #[\SensitiveParameter]
        array $row,
        #[\SensitiveParameter]
        string $assignedAt,
    ): void {
        $bootstrap = $state['missing'] ? 1 : 0;
        $segment = $row['segment_id'];
        $segmentVersion = 0;
        if (!$state['missing']) {
            $boundarySegment = $boundary['segment'];
            if ($boundarySegment === null) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }
            $segment = $boundarySegment['segmentId'];
            $segmentVersion = $boundarySegment['stateVersion'];
        }
        $nextSequence = $state['lastSequence'] + 1;
        $parameters = [
            'bootstrap' => $bootstrap,
            'partition_id' => $row['partition_id'],
            'tenant_type' => $row['tenant_type'],
            'tenant_id' => $row['tenant_id'],
            'active_segment_id' => $segment,
            'segment_id' => $segment,
            'record_id' => $row['record_id'],
            'schema_version' => $row['schema_version'],
            'kind' => $row['kind'],
            'next_sequence' => $nextSequence,
            'previous_record_hash' => $row['previous_record_hash'],
            'content_hash' => $row['content_hash'],
            'record_hash' => $row['record_hash'],
            'recorded_at' => $row['recorded_at'],
            'event_category' => $row['event_category'],
            'protected_body' => $row['protected_body'],
            'action_type' => $row['action_type'],
            'resource_type' => $row['resource_type'],
            'resource_id' => $row['resource_id'],
            'assigned_at' => $assignedAt,
            'state_version' => $state['stateVersion'],
            'segment_version' => $segmentVersion,
        ];
        $types = [
            'bootstrap' => ParameterType::INTEGER,
            'partition_id' => ParameterType::STRING,
            'tenant_type' => ParameterType::STRING,
            'tenant_id' => ParameterType::STRING,
            'active_segment_id' => ParameterType::STRING,
            'segment_id' => ParameterType::STRING,
            'record_id' => ParameterType::STRING,
            'schema_version' => ParameterType::INTEGER,
            'kind' => ParameterType::STRING,
            'next_sequence' => ParameterType::INTEGER,
            'previous_record_hash' => ParameterType::STRING,
            'content_hash' => ParameterType::STRING,
            'record_hash' => ParameterType::STRING,
            'recorded_at' => ParameterType::STRING,
            'event_category' => ParameterType::STRING,
            'protected_body' => ParameterType::BINARY,
            'action_type' => ParameterType::STRING,
            'resource_type' => ParameterType::STRING,
            'resource_id' => ParameterType::STRING,
            'assigned_at' => ParameterType::STRING,
            'state_version' => ParameterType::INTEGER,
            'segment_version' => ParameterType::INTEGER,
        ];
        try {
            $result = $this->connection->fetchOne($this->writeSql(), $parameters, $types);
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
        if ((string) $result !== '1') {
            throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
        }
    }

    private function writeSql(): string
    {
        $segments = $this->schema->segmentsTable();
        $states = $this->schema->partitionStateTable();
        $records = $this->schema->recordsTable();

        return "WITH new_segment AS (
                INSERT INTO {$segments} (
                    partition_id, segment_id, state, first_sequence, final_sequence,
                    head_record_hash, predecessor_record_hash, opened_at, closed_at,
                    successor_segment_id, close_fencing_token, state_version
                )
                SELECT :partition_id, CAST(:segment_id AS uuid), 'open', 1, CAST(:next_sequence AS bigint),
                       :record_hash, NULL, CAST(:assigned_at AS timestamptz), NULL, NULL, NULL, 1
                WHERE CAST(:bootstrap AS integer) = 1
                RETURNING partition_id
            ),
            new_state AS (
                INSERT INTO {$states} (
                    partition_id, tenant_type, tenant_id, active_segment_id,
                    last_sequence, head_record_hash, last_assigned_recorded_at, state_version
                )
                SELECT :partition_id, :tenant_type, :tenant_id, CAST(:active_segment_id AS uuid),
                       CAST(:next_sequence AS bigint), :record_hash, CAST(:assigned_at AS timestamptz), 1
                FROM new_segment
                WHERE CAST(:bootstrap AS integer) = 1
                RETURNING partition_id
            ),
            new_record AS (
                INSERT INTO {$records} (
                    schema_version, kind, record_id, partition_id, tenant_type, tenant_id,
                    segment_id, sequence, previous_record_hash, content_hash, record_hash,
                    recorded_at, event_category, protected_body, action_type, resource_type, resource_id
                )
                SELECT CAST(:schema_version AS smallint), :kind, CAST(:record_id AS uuid), :partition_id, :tenant_type, :tenant_id,
                       CAST(:segment_id AS uuid), CAST(:next_sequence AS bigint), :previous_record_hash, :content_hash, :record_hash,
                       CAST(:recorded_at AS timestamptz), :event_category, CAST(:protected_body AS bytea), :action_type, :resource_type, :resource_id
                FROM new_segment
                WHERE CAST(:bootstrap AS integer) = 1
                UNION ALL
                SELECT CAST(:schema_version AS smallint), :kind, CAST(:record_id AS uuid), :partition_id, :tenant_type, :tenant_id,
                       CAST(:segment_id AS uuid), CAST(:next_sequence AS bigint), :previous_record_hash, :content_hash, :record_hash,
                       CAST(:recorded_at AS timestamptz), :event_category, CAST(:protected_body AS bytea), :action_type, :resource_type, :resource_id
                WHERE CAST(:bootstrap AS integer) = 0
                RETURNING partition_id
            ),
            advanced_segment AS (
                UPDATE {$segments}
                SET final_sequence = CAST(:next_sequence AS bigint),
                    head_record_hash = :record_hash,
                    state_version = state_version + 1
                WHERE CAST(:bootstrap AS integer) = 0
                  AND partition_id = :partition_id
                  AND segment_id = CAST(:segment_id AS uuid)
                  AND state = 'open'
                  AND state_version = CAST(:segment_version AS bigint)
                  AND EXISTS (SELECT 1 FROM new_record)
                RETURNING partition_id
            ),
            advanced_state AS (
                UPDATE {$states}
                SET last_sequence = CAST(:next_sequence AS bigint),
                    head_record_hash = :record_hash,
                    last_assigned_recorded_at = CAST(:assigned_at AS timestamptz),
                    state_version = state_version + 1
                WHERE CAST(:bootstrap AS integer) = 0
                  AND partition_id = :partition_id
                  AND state_version = CAST(:state_version AS bigint)
                  AND EXISTS (SELECT 1 FROM advanced_segment)
                RETURNING partition_id
            ),
            fence AS (
                SELECT CAST(CASE WHEN (
                    (SELECT count(*) FROM new_record) = 1
                    AND (
                        CAST(:bootstrap AS integer) = 1
                        AND (SELECT count(*) FROM new_segment) = 1
                        AND (SELECT count(*) FROM new_state) = 1
                        AND (SELECT count(*) FROM advanced_segment) = 0
                        AND (SELECT count(*) FROM advanced_state) = 0
                        OR CAST(:bootstrap AS integer) = 0
                        AND (SELECT count(*) FROM new_segment) = 0
                        AND (SELECT count(*) FROM new_state) = 0
                        AND (SELECT count(*) FROM advanced_segment) = 1
                        AND (SELECT count(*) FROM advanced_state) = 1
                    )
                ) THEN '1' ELSE 'audit_store_abort' END AS bigint) AS committed
            )
            SELECT committed FROM fence";
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

    /**
     * A quarantined connection is closed on a best-effort basis; a second close
     * error must not replace the fixed failure already being reported.
     *
     * @mago-expect lint:no-empty-catch-clause
     */
    private function quarantine(): void
    {
        if ($this->quarantined) {
            return;
        }
        $this->quarantined = true;
        try {
            $this->connection->close();
        } catch (\Throwable) { // The connection is already unusable; preserve the fixed failure.
        }
    }

    private function safeFailure(
        #[\SensitiveParameter]
        \Throwable $failure,
        AuditStoreFailure $fallback = AuditStoreFailure::StorageUnavailable,
    ): AuditStoreException {
        if ($failure instanceof AuditStoreException) {
            return $failure;
        }
        if ($failure instanceof AuditProtectionException) {
            return new AuditStoreException(AuditStoreFailure::ResourceUnavailable);
        }
        if ($failure instanceof AuditValidationException) {
            return new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return new AuditStoreException($fallback);
    }

    private function assertPartitionValue(
        #[\SensitiveParameter]
        string $actual,
        #[\SensitiveParameter]
        string $expected,
    ): void {
        if ($actual !== $expected || preg_match(self::PARTITION_PATTERN, $actual) !== 1) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }

    private function assertTenantColumns(
        #[\SensitiveParameter]
        ?string $type,
        #[\SensitiveParameter]
        ?string $id,
        #[\SensitiveParameter]
        ?AuditTypedReference $expected,
    ): void {
        if ($expected === null) {
            if ($type !== null || $id !== null) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }

            return;
        }
        if ($type !== $expected->type || $id !== $expected->id) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }

    private function integer(#[\SensitiveParameter] mixed $value, int $minimum, int $maximum): int
    {
        if (is_int($value)) {
            if ($value < $minimum || $value > $maximum) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }

            return $value;
        }
        if (!is_string($value) || preg_match('/\A(?:0|[1-9][0-9]*)\z/', $value) !== 1) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        $maximumText = (string) $maximum;
        if (
            strlen($value) > strlen($maximumText)
            || strlen($value) === strlen($maximumText) && strcmp($value, $maximumText) > 0
        ) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        $integer = (int) $value;
        if ($integer < $minimum) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return $integer;
    }

    private function string(#[\SensitiveParameter] mixed $value): string
    {
        if (!is_string($value)) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return $value;
    }

    private function nullableString(#[\SensitiveParameter] mixed $value): ?string
    {
        if ($value !== null && !is_string($value)) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return $value;
    }

    private function nullableHash(#[\SensitiveParameter] mixed $value): ?string
    {
        $hash = $this->nullableString($value);
        if ($hash !== null && preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return $hash;
    }

    private function hash(#[\SensitiveParameter] mixed $value): string
    {
        $hash = $this->string($value);
        if (preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return $hash;
    }

    private function uuid(#[\SensitiveParameter] mixed $value): string
    {
        $uuid = $this->string($value);
        if (preg_match(self::UUID_V7_PATTERN, $uuid) !== 1) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return $uuid;
    }

    private function assertAutoCommit(): void
    {
        try {
            if ($this->connection->isAutoCommit()) {
                return;
            }
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
        }

        throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
    }

    private function assertRecordId(#[\SensitiveParameter] string $recordId): void
    {
        if (preg_match(self::UUID_V7_PATTERN, $recordId) !== 1) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }

    private function assertPartitionBinding(
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        #[\SensitiveParameter]
        string $partitionId,
    ): void {
        if (preg_match(self::PARTITION_PATTERN, $partitionId) !== 1) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        try {
            $derived = $this->partitions->derive($tenant);
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        if ($derived !== $partitionId) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }

    private function findSql(#[\SensitiveParameter] ?AuditTypedReference $tenant): string
    {
        $table = $this->schema->recordsTable();
        $tenantPredicate = $tenant === null
            ? 'tenant_type IS NULL AND tenant_id IS NULL'
            : 'tenant_type = :tenant_type AND tenant_id = :tenant_id';

        return "SELECT schema_version,
                kind,
                record_id::text AS record_id,
                partition_id,
                tenant_type,
                tenant_id,
                segment_id::text AS segment_id,
                sequence,
                previous_record_hash,
                content_hash,
                record_hash,
                to_char(recorded_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') AS recorded_at,
                event_category,
                protected_body,
                action_type,
                resource_type,
                resource_id
            FROM {$table}
            WHERE record_id = :record_id
              AND partition_id = :partition_id
              AND {$tenantPredicate}";
    }

    /** @return array<string,string> */
    private function findParameters(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        #[\SensitiveParameter]
        string $partitionId,
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

    /**
     * @param array{schema_version:mixed,kind:mixed,record_id:mixed,partition_id:mixed,tenant_type:mixed,tenant_id:mixed,segment_id:mixed,sequence:mixed,previous_record_hash:mixed,content_hash:mixed,record_hash:mixed,recorded_at:mixed,event_category:mixed,protected_body:mixed,action_type:mixed,resource_type:mixed,resource_id:mixed} $row
     * @return array<string,mixed>
     */
    private function hydratorRow(#[\SensitiveParameter] array $row): array
    {
        $result = [];
        foreach (self::HYDRATOR_COLUMNS as $column) {
            if (!array_key_exists($column, $row)) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }
            $result[$column] = $row[$column];
        }

        return $result;
    }

    /** @param array<string,mixed> $logical @param array<string,mixed> $row */
    private function assertQueryIndexes(#[\SensitiveParameter] array $logical, #[\SensitiveParameter] array $row): void
    {
        $action = $logical['action'] ?? null;
        $resource = $logical['resource'] ?? null;
        if (
            !is_array($action)
            || !is_array($resource)
            || !array_key_exists('type', $action)
            || !array_key_exists('type', $resource)
            || !array_key_exists('id', $resource)
            || !is_string($action['type'])
            || !is_string($resource['type'])
            || !is_string($resource['id'])
            || !is_string($row['action_type'])
            || !is_string($row['resource_type'])
            || !is_string($row['resource_id'])
            || $action['type'] !== $row['action_type']
            || $resource['type'] !== $row['resource_type']
            || $resource['id'] !== $row['resource_id']
        ) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }
}
