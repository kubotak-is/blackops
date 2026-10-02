<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Artifact;

final class AuditArtifactRelation
{
    /** @param array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string} $value */
    public static function checkpoint(#[\SensitiveParameter] array $value): void
    {
        $firstSequence = $value['firstSequence'];
        $lastSequence = $value['lastSequence'];
        $recordCount = $value['recordCount'];
        $firstRecordHash = $value['firstRecordHash'];
        $chainHeadHash = $value['chainHeadHash'];
        $previousCheckpointHash = $value['previousCheckpointHash'];
        if ($lastSequence < $firstSequence) {
            self::invalid();
        }
        if ($recordCount !== ($lastSequence - $firstSequence + 1)) {
            self::invalid();
        }
        if ($firstSequence === 1 && $previousCheckpointHash !== null) {
            self::invalid();
        }
        if ($firstSequence !== 1 && $previousCheckpointHash === null) {
            self::invalid();
        }
        if ($recordCount === 1 && !hash_equals($firstRecordHash, $chainHeadHash)) {
            self::invalid();
        }
    }

    /** @param array{schemaVersion:int,kind:string,anchorReceiptId:string,partitionId:string,segmentId:string,checkpointId:string,checkpointHash:string,checkpointBytesHash:string,anchorStoreRef:string,anchorObjectRef:string,anchorObjectVersion:int,previousAnchorReceiptHash:?string,anchoredAt:string,signedAt:string,signatureAlgorithm:string,signingKeyRef:string} $value */
    public static function anchor(#[\SensitiveParameter] array $value): void
    {
        $anchorObjectVersion = $value['anchorObjectVersion'];
        $previousAnchorReceiptHash = $value['previousAnchorReceiptHash'];
        if ($anchorObjectVersion === 1 && $previousAnchorReceiptHash !== null) {
            self::invalid();
        }
        if ($anchorObjectVersion !== 1 && $previousAnchorReceiptHash === null) {
            self::invalid();
        }
    }

    private static function invalid(): never
    {
        throw new AuditArtifactException(AuditArtifactFailure::ArtifactInvalid);
    }
}
