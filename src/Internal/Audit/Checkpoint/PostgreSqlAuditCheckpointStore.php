<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\Protection\AuditProtectedBodyCodec;
use BlackOps\Internal\Audit\Record\AuditPartitionIdDeriver;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditSchema;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditStoredRecordHydrator;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/**
 * Owns the two checkpoint transaction roots and the immutable preparation and
 * complete rows that they commit.
 */
final class PostgreSqlAuditCheckpointStore
{
    private const string UUID_V7 = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';

    private const string PARTITION = '/^(?:system:global|tenant:[a-f0-9]{64})$/D';

    private readonly PostgreSqlAuditClosedSegmentReader $reader;

    private readonly PostgreSqlAuditCheckpointTransaction $transaction;

    private readonly PostgreSqlAuditCheckpointRows $rows;

    private readonly PostgreSqlAuditCheckpointPredecessor $predecessor;

    private readonly PostgreSqlAuditCheckpointTrustWorkflow $trustWorkflow;

    private readonly AuditPartitionIdDeriver $partitions;

    private readonly PostgreSqlAuditCheckpointPreparationWorkflow $preparationWorkflow;

    public function __construct(
        Connection $connection,
        PostgreSqlAuditCheckpointSchema $schema,
        PostgreSqlAuditStoredRecordHydrator $hydrator,
        AuditProtectedBodyCodec $protection,
        AuditPartitionIdDeriver $partitions,
        ClockInterface $clock,
        Uuidv7Generator $ids,
    ) {
        $b2Schema = new PostgreSqlAuditSchema($schema->schemaName());
        $this->reader = new PostgreSqlAuditClosedSegmentReader(
            $connection,
            $b2Schema,
            $hydrator,
            $protection,
            $partitions,
        );
        $this->transaction = new PostgreSqlAuditCheckpointTransaction($connection);
        $this->rows = new PostgreSqlAuditCheckpointRows($connection, $schema, $b2Schema);
        $this->predecessor = new PostgreSqlAuditCheckpointPredecessor($this->rows);
        $this->trustWorkflow = new PostgreSqlAuditCheckpointTrustWorkflow($clock, $ids);
        $this->partitions = $partitions;
        $this->preparationWorkflow = new PostgreSqlAuditCheckpointPreparationWorkflow(
            $this->reader,
            $this->transaction,
            $this->rows,
            $this->predecessor,
            $this->trustWorkflow,
        );
    }

    /** @param array<string,mixed> $trustedSnapshot */
    public function prepare(
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $segmentId,
        #[\SensitiveParameter]
        string $signingKeyRef,
        #[\SensitiveParameter]
        array $trustedSnapshot,
    ): AuditCheckpointPreparation {
        $this->assertBinding($expectedTenant, $partitionId, $segmentId);

        return $this->preparationWorkflow->run(
            $expectedTenant,
            $partitionId,
            $segmentId,
            $signingKeyRef,
            $trustedSnapshot,
        );
    }

    /** @param array<string,mixed> $trustedSnapshot */
    public function persist(
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $segmentId,
        #[\SensitiveParameter]
        string $completeBytes,
        #[\SensitiveParameter]
        array $trustedSnapshot,
    ): void {
        $this->assertBinding($expectedTenant, $partitionId, $segmentId);
        /** @var array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string,checkpointHash:string,signature:string} $complete */
        $complete = PostgreSqlAuditCheckpointBinding::completeInput($completeBytes);
        $suppliedUnsigned = $complete;
        unset($suppliedUnsigned['checkpointHash'], $suppliedUnsigned['signature']);
        /** @var array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string} $suppliedUnsigned */
        $this->transaction->beginRoot();
        try {
            $proof = $this->reader->proveInCurrentTransaction($expectedTenant, $partitionId, $segmentId);
            $preparationRow = $this->rows->preparation($partitionId, $segmentId);
            if ($preparationRow === false) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }
            $preparation = AuditCheckpointPreparation::fromRow($preparationRow);
            $boundary = $preparation->boundary();
            $preparedUnsigned = $preparation->unsigned();
            $previous = $this->predecessor->load($proof['previousSegmentId'], $partitionId);
            PostgreSqlAuditCheckpointBinding::preparation($boundary, $proof, $preparedUnsigned, $previous);
            PostgreSqlAuditCheckpointBinding::persistUnsigned($suppliedUnsigned, $preparedUnsigned);
            $existing = $this->rows->complete($complete['checkpointId']);
            if ($existing !== false) {
                PostgreSqlAuditCheckpointBinding::completeReplay(
                    $existing,
                    $complete,
                    $completeBytes,
                    $complete['checkpointId'],
                );
                $this->transaction->commit();

                return;
            }
            if ($proof['state'] !== 'closed' || $proof['stateVersion'] !== $boundary['closedStateVersion']) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }
            $this->trustWorkflow->verifyPersistence($preparation, $previous, $completeBytes, $trustedSnapshot);
            $parameters = [
                'checkpoint_id' => $complete['checkpointId'],
                'checkpoint_hash' => $complete['checkpointHash'],
                'complete_bytes' => $completeBytes,
            ];
            if ($this->rows->insertComplete($parameters) !== 1) {
                throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
            }
            $this->transaction->commit();
        } catch (\Throwable $failure) {
            $mapped = $this->transaction->abort($failure);
            throw new AuditStoreException($mapped);
        }
    }

    private function assertBinding(
        #[\SensitiveParameter]
        ?AuditTypedReference $tenant,
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $segmentId,
    ): void {
        if (preg_match(self::PARTITION, $partitionId) !== 1 || preg_match(self::UUID_V7, $segmentId) !== 1) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        try {
            if ($this->partitions->derive($tenant) !== $partitionId) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }
        } catch (AuditStoreException $failure) {
            throw new AuditStoreException($failure->failure());
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }
}
