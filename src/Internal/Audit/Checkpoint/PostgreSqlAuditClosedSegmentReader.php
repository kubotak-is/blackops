<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Protection\AuditProtectionException;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditStoredRecordHydrator;
use Doctrine\DBAL\Connection;

/**
 * Proves a closed audit segment while the caller owns one transaction root.
 * The seam reads the current graph and record chain but never owns the root.
 */
final class PostgreSqlAuditClosedSegmentReader
{
    private const string UUID_V7 = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';

    private const string PARTITION = '/^(?:system:global|tenant:[a-f0-9]{64})$/D';

    private readonly Connection $connection;

    private readonly AuditPartitionIdDeriver $partitions;

    private readonly PostgreSqlAuditClosedSegmentQueries $queries;

    private readonly PostgreSqlAuditClosedSegmentRecordProof $recordProof;

    public function __construct(
        Connection $connection,
        PostgreSqlAuditSchema $schema,
        PostgreSqlAuditStoredRecordHydrator $hydrator,
        AuditProtectedBodyCodec $protection,
        AuditPartitionIdDeriver $partitions,
    ) {
        $this->connection = $connection;
        $this->partitions = $partitions;
        $this->queries = new PostgreSqlAuditClosedSegmentQueries($connection, $schema);
        $this->recordProof = new PostgreSqlAuditClosedSegmentRecordProof($this->queries, $hydrator, $protection);
    }

    /** @return array{partitionId:string,segmentId:string,state:'closed'|'sealed',stateVersion:int,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,predecessorRecordHash:?string,previousSegmentId:?string,successorSegmentId:string,closeFencingToken:string,closedAt:string} */
    public function proveInCurrentTransaction(
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $segmentId,
    ): array {
        $this->assertConnection();
        $this->assertIdentifiers($partitionId, $segmentId);
        try {
            if ($this->partitions->derive($expectedTenant) !== $partitionId) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }
            $this->queries->lockPartition($partitionId);
            $state = PostgreSqlAuditClosedSegmentGraph::state(
                $this->queries->state($expectedTenant, $partitionId),
                $expectedTenant,
                $partitionId,
            );
            $target = PostgreSqlAuditClosedSegmentGraph::target(
                $this->queries->target($partitionId, $segmentId),
                $state,
                $partitionId,
                $segmentId,
            );
            $successor = PostgreSqlAuditClosedSegmentGraph::successor(
                $this->queries->successor($partitionId, $target['successorSegmentId']),
                $target,
                $partitionId,
                $segmentId,
            );
            $previous = PostgreSqlAuditClosedSegmentGraph::previous(
                $this->queries->previous($partitionId, $segmentId),
                $target,
                $partitionId,
                $segmentId,
            );
            $endpoint = $target['firstSequence'] === 1
                ? false
                : $this->queries->endpoint($expectedTenant, $partitionId, $target['firstSequence'] - 1);
            PostgreSqlAuditClosedSegmentGraph::endpoint($endpoint, $target, $previous);
            $records = $this->recordProof->prove($expectedTenant, $partitionId, $segmentId, $target);

            return $this->proofResult($target, $successor, $previous, $records);
        } catch (\Throwable $failure) {
            throw $this->safeFailure($failure);
        }
    }

    /**
     * @param array{partitionId:string,segmentId:string,state:'closed'|'sealed',firstSequence:int,finalSequence:int,headRecordHash:string,predecessorRecordHash:?string,openedAt:string,closedAt:string,successorSegmentId:string,closeFencingToken:string,stateVersion:int} $target
     * @param array{partitionId:string,segmentId:string,state:'open'|'closed'|'sealed',firstSequence:int,finalSequence:?int,headRecordHash:?string,predecessorRecordHash:?string,openedAt:string,closedAt:?string,successorSegmentId:?string,closeFencingToken:?string,stateVersion:int} $successor
     * @param array{partitionId:string,segmentId:string,state:'closed'|'sealed',firstSequence:int,finalSequence:int,headRecordHash:string,predecessorRecordHash:?string,openedAt:string,closedAt:string,successorSegmentId:string,closeFencingToken:string,stateVersion:int}|null $previous
     * @param array{recordCount:int,firstRecordHash:string,chainHeadHash:string} $records
     * @return array{partitionId:string,segmentId:string,state:'closed'|'sealed',stateVersion:int,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,predecessorRecordHash:?string,previousSegmentId:?string,successorSegmentId:string,closeFencingToken:string,closedAt:string}
     */
    private function proofResult(
        #[\SensitiveParameter]
        array $target,
        #[\SensitiveParameter]
        array $successor,
        #[\SensitiveParameter]
        ?array $previous,
        #[\SensitiveParameter]
        array $records,
    ): array {
        return [
            'partitionId' => $target['partitionId'],
            'segmentId' => $target['segmentId'],
            'state' => $target['state'],
            'stateVersion' => $target['stateVersion'],
            'firstSequence' => $target['firstSequence'],
            'lastSequence' => $target['finalSequence'],
            'recordCount' => $records['recordCount'],
            'firstRecordHash' => $records['firstRecordHash'],
            'chainHeadHash' => $records['chainHeadHash'],
            'predecessorRecordHash' => $target['predecessorRecordHash'],
            'previousSegmentId' => $previous['segmentId'] ?? null,
            'successorSegmentId' => $successor['segmentId'],
            'closeFencingToken' => $target['closeFencingToken'],
            'closedAt' => $target['closedAt'],
        ];
    }

    private function assertConnection(): void
    {
        try {
            if (
                $this->connection->isAutoCommit() !== true
                || $this->connection->isTransactionActive() !== true
                || $this->connection->getTransactionNestingLevel() !== 1
            ) {
                throw new \RuntimeException();
            }
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
        }
    }

    private function assertIdentifiers(
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $segmentId,
    ): void {
        if (preg_match(self::PARTITION, $partitionId) !== 1 || preg_match(self::UUID_V7, $segmentId) !== 1) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }

    private function safeFailure(#[\SensitiveParameter] \Throwable $failure): AuditStoreException
    {
        if ($failure instanceof AuditStoreException) {
            return new AuditStoreException($failure->failure());
        }
        if ($failure instanceof AuditProtectionException) {
            return new AuditStoreException(AuditStoreFailure::ResourceUnavailable);
        }
        if ($failure instanceof AuditValidationException) {
            return new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }

        return new AuditStoreException(AuditStoreFailure::StorageUnavailable);
    }
}
