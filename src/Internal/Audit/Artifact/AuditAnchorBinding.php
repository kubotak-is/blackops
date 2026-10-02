<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Artifact;

final class AuditAnchorBinding
{
    /**
     * @param array{schemaVersion:int,kind:string,anchorReceiptId:string,partitionId:string,segmentId:string,checkpointId:string,checkpointHash:string,checkpointBytesHash:string,anchorStoreRef:string,anchorObjectRef:string,anchorObjectVersion:int,previousAnchorReceiptHash:?string,anchoredAt:string,signedAt:string,signatureAlgorithm:string,signingKeyRef:string} $unsigned
     * @param array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string,checkpointHash:string,signature:string} $checkpoint
     */
    public static function assert(
        #[\SensitiveParameter]
        array $unsigned,
        #[\SensitiveParameter]
        array $checkpoint,
        #[\SensitiveParameter]
        string $checkpointBytes,
    ): void {
        try {
            if (
                $unsigned['partitionId'] !== $checkpoint['partitionId']
                || $unsigned['segmentId'] !== $checkpoint['segmentId']
                || $unsigned['checkpointId'] !== $checkpoint['checkpointId']
                || !hash_equals($unsigned['checkpointHash'], $checkpoint['checkpointHash'])
                || !hash_equals($unsigned['checkpointBytesHash'], AuditCheckpointCodec::bytesHash($checkpointBytes))
                || $checkpoint['signedAt'] > $unsigned['anchoredAt']
                || $unsigned['anchoredAt'] > $unsigned['signedAt']
            ) {
                self::invalid();
            }
        } catch (\Throwable) {
            self::invalid();
        }
    }

    private static function invalid(): never
    {
        throw new AuditArtifactException(AuditArtifactFailure::ArtifactInvalid);
    }
}
