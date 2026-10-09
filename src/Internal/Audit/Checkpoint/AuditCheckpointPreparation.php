<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Checkpoint;

use BlackOps\Internal\Audit\Storage\AuditStoreException;
use BlackOps\Internal\Audit\Storage\AuditStoreFailure;

/**
 * The durable, unsigned side of a checkpoint.
 *
 * It keeps the canonical bytes and the database boundary together so callers
 * cannot replace either half while recovering an interrupted signing call.
 */
final readonly class AuditCheckpointPreparation
{
    private \SensitiveParameterValue $snapshot;

    /**
     * @param array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string} $unsigned
     * @param array{partitionId:string,segmentId:string,closedStateVersion:int,closeFencingToken:string} $boundary
     */
    private function __construct(
        #[\SensitiveParameter]
        array $unsigned,
        #[\SensitiveParameter]
        array $boundary,
        #[\SensitiveParameter]
        string $fingerprint,
    ) {
        $this->snapshot = new \SensitiveParameterValue([
            'unsigned' => $unsigned,
            'boundary' => $boundary,
            'fingerprint' => $fingerprint,
        ]);
    }

    /**
     * @param array<array-key,mixed> $row
     */
    public static function fromRow(#[\SensitiveParameter] array $row): self
    {
        try {
            $shape = AuditCheckpointPreparationRowShape::read($row);
            $unsigned = AuditCheckpointPreparationBody::read($shape['unsignedBytes']);
            if (
                !hash_equals($unsigned['checkpointId'], $shape['checkpointId'])
                || !hash_equals($unsigned['partitionId'], $shape['partitionId'])
                || !hash_equals($unsigned['segmentId'], $shape['segmentId'])
            ) {
                throw new \RuntimeException();
            }

            return new self(
                $unsigned,
                [
                    'partitionId' => $shape['partitionId'],
                    'segmentId' => $shape['segmentId'],
                    'closedStateVersion' => $shape['closedStateVersion'],
                    'closeFencingToken' => $shape['closeFencingToken'],
                ],
                $shape['signingKeyFingerprint'],
            );
        } catch (\Throwable) {
            throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
        }
    }

    /** @return array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string} */
    public function unsigned(): array
    {
        /**
         * @var array{
         *     unsigned:array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string},
         *     boundary:array{partitionId:string,segmentId:string,closedStateVersion:int,closeFencingToken:string},
         *     fingerprint:string
         * } $snapshot
         */
        $snapshot = $this->snapshot->getValue();

        return $snapshot['unsigned'];
    }

    /** @return array{partitionId:string,segmentId:string,closedStateVersion:int,closeFencingToken:string} */
    public function boundary(): array
    {
        /**
         * @var array{
         *     unsigned:array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string},
         *     boundary:array{partitionId:string,segmentId:string,closedStateVersion:int,closeFencingToken:string},
         *     fingerprint:string
         * } $snapshot
         */
        $snapshot = $this->snapshot->getValue();

        return $snapshot['boundary'];
    }

    public function signingKeyFingerprint(): string
    {
        /**
         * @var array{
         *     unsigned:array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string},
         *     boundary:array{partitionId:string,segmentId:string,closedStateVersion:int,closeFencingToken:string},
         *     fingerprint:string
         * } $snapshot
         */
        $snapshot = $this->snapshot->getValue();

        return $snapshot['fingerprint'];
    }

    /** @return array<string,never> */
    public function __debugInfo(): array
    {
        return [];
    }

    /** @return array<string,never> */
    public function __serialize(): array
    {
        return [];
    }

    /** @param array<string,mixed> $data */
    public function __unserialize(#[\SensitiveParameter] array $data): void
    {
        throw new AuditStoreException(AuditStoreFailure::IntegrityInvalid);
    }
}
