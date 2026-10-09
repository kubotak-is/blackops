<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;

final readonly class PostgreSqlAuditCheckpointPredecessor
{
    public function __construct(
        private PostgreSqlAuditCheckpointRows $rows,
    ) {}

    /**
     * @return array{bytes:string,hash:string,value:array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string,checkpointHash:string,signature:string}}|null
     */
    public function load(#[\SensitiveParameter] ?string $segmentId, #[\SensitiveParameter] string $partitionId): ?array
    {
        if ($segmentId === null) {
            return null;
        }
        $segment = $this->rows->predecessorSegment($partitionId, $segmentId);
        $row = $this->rows->predecessorCheckpoint($partitionId, $segmentId);
        try {
            if ($segment === false || $row === false) {
                throw new \RuntimeException();
            }

            return $this->read($segment, $row, $partitionId, $segmentId);
        } catch (AuditStoreException $failure) {
            throw new AuditStoreException($failure->failure());
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }

    /**
     * @param array<array-key,mixed> $segment
     * @param array<array-key,mixed> $row
     * @return array{bytes:string,hash:string,value:array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string,checkpointHash:string,signature:string}}
     */
    private function read(
        #[\SensitiveParameter]
        array $segment,
        #[\SensitiveParameter]
        array $row,
        #[\SensitiveParameter]
        string $partitionId,
        #[\SensitiveParameter]
        string $segmentId,
    ): array {
        if (
            array_keys($row) !== [
                'checkpoint_id',
                'partition_id',
                'segment_id',
                'closed_state_version',
                'close_fencing_token',
                'unsigned_checkpoint',
                'signing_key_fingerprint',
                'checkpoint_hash',
                'complete_bytes',
            ]
        ) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        $parsed = PostgreSqlAuditSegmentRowParser::parse($segment, $partitionId, $segmentId);
        if (!in_array($parsed['state'], ['closed', 'sealed'], strict: true)) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        /** @var array{partitionId:string,segmentId:string,state:'closed'|'sealed',firstSequence:int,finalSequence:int,headRecordHash:string,predecessorRecordHash:?string,openedAt:string,closedAt:string,successorSegmentId:string,closeFencingToken:string,stateVersion:int} $parsed */
        if (
            $parsed['stateVersion'] < 2
            || $row['partition_id'] !== $partitionId
            || $row['segment_id'] !== $segmentId
            || !is_string($row['checkpoint_id'])
        ) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
        $preparationRow = [
            'checkpoint_id' => $row['checkpoint_id'],
            'partition_id' => $row['partition_id'],
            'segment_id' => $row['segment_id'],
            'closed_state_version' => $row['closed_state_version'],
            'close_fencing_token' => $row['close_fencing_token'],
            'unsigned_checkpoint' => $row['unsigned_checkpoint'],
            'signing_key_fingerprint' => $row['signing_key_fingerprint'],
        ];
        $preparation = AuditCheckpointPreparation::fromRow($preparationRow);
        $completeBytes = $this->bytea($row['complete_bytes']);
        $complete = PostgreSqlAuditCheckpointBinding::completeInput($completeBytes);
        $unsigned = $complete;
        unset($unsigned['checkpointHash'], $unsigned['signature']);
        /** @var array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string} $unsigned */
        $boundary = $preparation->boundary();
        $preparedUnsigned = $preparation->unsigned();
        PostgreSqlAuditCheckpointBinding::stateVersion(
            $parsed['state'],
            $boundary['closedStateVersion'],
            $parsed['stateVersion'],
        );
        $storedHash = $row['checkpoint_hash'];
        /** @var string $checkpointId */
        $checkpointId = $row['checkpoint_id'];
        $preparationId = $preparedUnsigned['checkpointId'];
        PostgreSqlAuditCheckpointStoredBinding::identity($checkpointId, $storedHash, $complete, $preparationId);
        /** @var string $validatedStoredHash */
        $validatedStoredHash = $storedHash;
        PostgreSqlAuditCheckpointBinding::persistUnsigned($unsigned, $preparedUnsigned);
        PostgreSqlAuditCheckpointSegmentBinding::predecessor($boundary, $parsed, $unsigned);

        return ['bytes' => $completeBytes, 'hash' => $validatedStoredHash, 'value' => $complete];
    }

    private function bytea(#[\SensitiveParameter] mixed $value): string
    {
        return PostgreSqlAuditCheckpointBytes::database($value);
    }
}
