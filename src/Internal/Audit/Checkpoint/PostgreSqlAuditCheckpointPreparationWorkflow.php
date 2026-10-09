<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;

final readonly class PostgreSqlAuditCheckpointPreparationWorkflow
{
    private const int MAX_SAFE_INTEGER = 9_007_199_254_740_991;

    public function __construct(
        private PostgreSqlAuditClosedSegmentReader $reader,
        private PostgreSqlAuditCheckpointTransaction $transaction,
        private PostgreSqlAuditCheckpointRows $rows,
        private PostgreSqlAuditCheckpointPredecessor $predecessor,
        private PostgreSqlAuditCheckpointTrustWorkflow $trustWorkflow,
    ) {}

    /** @param array<string,mixed> $trustedSnapshot */
    public function run(
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
        $this->transaction->beginRoot();
        try {
            $proof = $this->reader->proveInCurrentTransaction($expectedTenant, $partitionId, $segmentId);
            $preparationRow = $this->rows->preparation($partitionId, $segmentId);
            $previous = $this->predecessor->load($proof['previousSegmentId'], $partitionId);
            if ($preparationRow !== false) {
                return $this->retry($proof, $preparationRow, $previous, $signingKeyRef);
            }

            return $this->create($proof, $previous, $signingKeyRef, $trustedSnapshot);
        } catch (\Throwable $failure) {
            $mapped = $this->transaction->abort($failure);
            throw new AuditStoreException($mapped);
        }
    }

    /**
     * @param array{partitionId:string,segmentId:string,state:'closed'|'sealed',stateVersion:int,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,predecessorRecordHash:?string,previousSegmentId:?string,successorSegmentId:string,closeFencingToken:string,closedAt:string} $proof
     * @param array<array-key,mixed> $preparationRow
     * @param array{bytes:string,hash:string,value:array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string,checkpointHash:string,signature:string}}|null $previous
     */
    private function retry(
        #[\SensitiveParameter]
        array $proof,
        #[\SensitiveParameter]
        array $preparationRow,
        #[\SensitiveParameter]
        ?array $previous,
        #[\SensitiveParameter]
        string $signingKeyRef,
    ): AuditCheckpointPreparation {
        $preparation = AuditCheckpointPreparation::fromRow($preparationRow);
        $boundary = $preparation->boundary();
        $unsigned = $preparation->unsigned();
        PostgreSqlAuditCheckpointBinding::preparation($boundary, $proof, $unsigned, $previous);
        if ($unsigned['signingKeyRef'] !== $signingKeyRef) {
            throw new AuditStoreException(AuditStoreFailure::IdempotencyConflict);
        }
        if ($proof['state'] === 'sealed') {
            $complete = $this->rows->complete($unsigned['checkpointId']);
            if ($complete === false) {
                throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
            }
            PostgreSqlAuditCheckpointBinding::sealedReplay($complete, $unsigned, $unsigned['checkpointId']);
        }
        $this->transaction->commit();

        return $preparation;
    }

    /**
     * @param array{partitionId:string,segmentId:string,state:'closed'|'sealed',stateVersion:int,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,predecessorRecordHash:?string,previousSegmentId:?string,successorSegmentId:string,closeFencingToken:string,closedAt:string} $proof
     * @param array{bytes:string,hash:string,value:array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string,checkpointHash:string,signature:string}}|null $previous
     * @param array<string,mixed> $trustedSnapshot
     */
    private function create(
        #[\SensitiveParameter]
        array $proof,
        #[\SensitiveParameter]
        ?array $previous,
        #[\SensitiveParameter]
        string $signingKeyRef,
        #[\SensitiveParameter]
        array $trustedSnapshot,
    ): AuditCheckpointPreparation {
        if ($proof['state'] !== 'closed' || $proof['stateVersion'] >= self::MAX_SAFE_INTEGER) {
            throw new AuditStoreException(
                $proof['state'] !== 'closed'
                    ? AuditStoreFailure::SegmentConflict
                    : AuditStoreFailure::ResourceUnavailable,
            );
        }
        /** @var array{unsigned:array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string},unsignedBytes:string,signingKeyFingerprint:string} $created */
        $created = $this->trustWorkflow->newPreparation($proof, $previous, $signingKeyRef, $trustedSnapshot);
        /** @var array{checkpoint_id:string,partition_id:string,segment_id:string,closed_state_version:int,close_fencing_token:string,unsigned_checkpoint:string,signing_key_fingerprint:string} $row */
        $row = [
            'checkpoint_id' => $created['unsigned']['checkpointId'],
            'partition_id' => $proof['partitionId'],
            'segment_id' => $proof['segmentId'],
            'closed_state_version' => $proof['stateVersion'],
            'close_fencing_token' => $proof['closeFencingToken'],
            'unsigned_checkpoint' => $created['unsignedBytes'],
            'signing_key_fingerprint' => $created['signingKeyFingerprint'],
        ];
        $preparation = AuditCheckpointPreparation::fromRow($row);
        PostgreSqlAuditCheckpointBinding::preparation(
            $preparation->boundary(),
            $proof,
            $preparation->unsigned(),
            $previous,
        );
        if ($this->rows->checkpointIdExists($created['unsigned']['checkpointId'])) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        if ($this->rows->insertPreparation($row) !== 1) {
            throw new AuditStoreException(AuditStoreFailure::StorageUnavailable);
        }
        $this->transaction->commit();

        return $preparation;
    }
}
