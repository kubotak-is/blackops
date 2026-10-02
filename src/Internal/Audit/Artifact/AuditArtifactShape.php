<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Artifact;

use BlackOps\Core\Audit\AuditRecordTime;

final class AuditArtifactShape
{
    /**
     * @param array<string,mixed> $value
     * @return array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string}
     */
    public static function checkpointUnsigned(#[\SensitiveParameter] array $value): array
    {
        $value = AuditArtifactScalar::closed($value, [
            'schemaVersion',
            'kind',
            'checkpointId',
            'partitionId',
            'segmentId',
            'firstSequence',
            'lastSequence',
            'recordCount',
            'firstRecordHash',
            'chainHeadHash',
            'previousCheckpointHash',
            'sealedAt',
            'signedAt',
            'hashSuite',
            'signatureAlgorithm',
            'signingKeyRef',
        ]);
        $normalized = self::checkpointFields($value);
        try {
            AuditRecordTime::assertTimestamp($normalized['sealedAt']);
            AuditRecordTime::assertTimestamp($normalized['signedAt']);
        } catch (\Throwable) {
            self::invalid();
        }
        if (
            [
                $normalized['schemaVersion'],
                $normalized['kind'],
                $normalized['hashSuite'],
                $normalized['signatureAlgorithm'],
            ] !== [1, 'audit_checkpoint', 'audit-sha256-jcs-v1', 'ed25519-v1']
        ) {
            self::invalid();
        }
        if ($normalized['sealedAt'] > $normalized['signedAt']) {
            self::invalid();
        }
        return $normalized;
    }

    /**
     * @param array<string,mixed> $value
     * @return array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string,checkpointHash:string,signature:string}
     */
    public static function checkpointComplete(#[\SensitiveParameter] array $value): array
    {
        $value = AuditArtifactScalar::closed($value, [
            'schemaVersion',
            'kind',
            'checkpointId',
            'partitionId',
            'segmentId',
            'firstSequence',
            'lastSequence',
            'recordCount',
            'firstRecordHash',
            'chainHeadHash',
            'previousCheckpointHash',
            'sealedAt',
            'signedAt',
            'hashSuite',
            'signatureAlgorithm',
            'signingKeyRef',
            'checkpointHash',
            'signature',
        ]);
        $checkpointHash = self::digest($value['checkpointHash']);
        $signature = AuditArtifactScalar::string($value['signature']);
        unset($value['checkpointHash'], $value['signature']);
        return [
            ...self::checkpointUnsigned($value),
            'checkpointHash' => $checkpointHash,
            'signature' => $signature,
        ];
    }

    /**
     * @param array<string,mixed> $value
     * @return array{schemaVersion:int,kind:string,anchorReceiptId:string,partitionId:string,segmentId:string,checkpointId:string,checkpointHash:string,checkpointBytesHash:string,anchorStoreRef:string,anchorObjectRef:string,anchorObjectVersion:int,previousAnchorReceiptHash:?string,anchoredAt:string,signedAt:string,signatureAlgorithm:string,signingKeyRef:string}
     */
    public static function anchorUnsigned(#[\SensitiveParameter] array $value): array
    {
        $value = AuditArtifactScalar::closed($value, [
            'schemaVersion',
            'kind',
            'anchorReceiptId',
            'partitionId',
            'segmentId',
            'checkpointId',
            'checkpointHash',
            'checkpointBytesHash',
            'anchorStoreRef',
            'anchorObjectRef',
            'anchorObjectVersion',
            'previousAnchorReceiptHash',
            'anchoredAt',
            'signedAt',
            'signatureAlgorithm',
            'signingKeyRef',
        ]);
        $normalized = self::anchorFields($value);
        try {
            AuditRecordTime::assertTimestamp($normalized['anchoredAt']);
            AuditRecordTime::assertTimestamp($normalized['signedAt']);
        } catch (\Throwable) {
            self::invalid();
        }
        if (
            [$normalized['schemaVersion'], $normalized['kind'], $normalized['signatureAlgorithm']] !== [
                1,
                'audit_anchor_receipt',
                'ed25519-v1',
            ]
        ) {
            self::invalid();
        }
        if ($normalized['anchoredAt'] > $normalized['signedAt']) {
            self::invalid();
        }
        return $normalized;
    }

    /**
     * @param array<string,mixed> $value
     * @return array{schemaVersion:int,kind:string,anchorReceiptId:string,partitionId:string,segmentId:string,checkpointId:string,checkpointHash:string,checkpointBytesHash:string,anchorStoreRef:string,anchorObjectRef:string,anchorObjectVersion:int,previousAnchorReceiptHash:?string,anchoredAt:string,signedAt:string,signatureAlgorithm:string,signingKeyRef:string,anchorReceiptHash:string,signature:string}
     */
    public static function anchorComplete(#[\SensitiveParameter] array $value): array
    {
        $value = AuditArtifactScalar::closed($value, [
            'schemaVersion',
            'kind',
            'anchorReceiptId',
            'partitionId',
            'segmentId',
            'checkpointId',
            'checkpointHash',
            'checkpointBytesHash',
            'anchorStoreRef',
            'anchorObjectRef',
            'anchorObjectVersion',
            'previousAnchorReceiptHash',
            'anchoredAt',
            'signedAt',
            'signatureAlgorithm',
            'signingKeyRef',
            'anchorReceiptHash',
            'signature',
        ]);
        $anchorReceiptHash = self::digest($value['anchorReceiptHash']);
        $signature = AuditArtifactScalar::string($value['signature']);
        unset($value['anchorReceiptHash'], $value['signature']);
        return [
            ...self::anchorUnsigned($value),
            'anchorReceiptHash' => $anchorReceiptHash,
            'signature' => $signature,
        ];
    }

    /**
     * @param array<string,mixed> $value
     * @return array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string}
     */
    private static function checkpointFields(#[\SensitiveParameter] array $value): array
    {
        $previousCheckpointHash = AuditArtifactScalar::nullableString($value['previousCheckpointHash']);
        if ($previousCheckpointHash !== null) {
            $previousCheckpointHash = self::digest($previousCheckpointHash);
        }
        return [
            'schemaVersion' => AuditArtifactScalar::boundedInteger($value['schemaVersion']),
            'kind' => AuditArtifactScalar::string($value['kind']),
            'checkpointId' => AuditArtifactScalar::string($value['checkpointId']),
            'partitionId' => self::partition($value['partitionId']),
            'segmentId' => self::segment($value['segmentId']),
            'firstSequence' => AuditArtifactScalar::boundedInteger($value['firstSequence']),
            'lastSequence' => AuditArtifactScalar::boundedInteger($value['lastSequence']),
            'recordCount' => AuditArtifactScalar::boundedInteger($value['recordCount']),
            'firstRecordHash' => self::digest($value['firstRecordHash']),
            'chainHeadHash' => self::digest($value['chainHeadHash']),
            'previousCheckpointHash' => $previousCheckpointHash,
            'sealedAt' => AuditArtifactScalar::string($value['sealedAt']),
            'signedAt' => AuditArtifactScalar::string($value['signedAt']),
            'hashSuite' => AuditArtifactScalar::string($value['hashSuite']),
            'signatureAlgorithm' => AuditArtifactScalar::string($value['signatureAlgorithm']),
            'signingKeyRef' => AuditArtifactScalar::string($value['signingKeyRef']),
        ];
    }

    /**
     * @param array<string,mixed> $value
     * @return array{schemaVersion:int,kind:string,anchorReceiptId:string,partitionId:string,segmentId:string,checkpointId:string,checkpointHash:string,checkpointBytesHash:string,anchorStoreRef:string,anchorObjectRef:string,anchorObjectVersion:int,previousAnchorReceiptHash:?string,anchoredAt:string,signedAt:string,signatureAlgorithm:string,signingKeyRef:string}
     */
    private static function anchorFields(#[\SensitiveParameter] array $value): array
    {
        $previousAnchorReceiptHash = AuditArtifactScalar::nullableString($value['previousAnchorReceiptHash']);
        if ($previousAnchorReceiptHash !== null) {
            $previousAnchorReceiptHash = self::digest($previousAnchorReceiptHash);
        }
        return [
            'schemaVersion' => AuditArtifactScalar::boundedInteger($value['schemaVersion']),
            'kind' => AuditArtifactScalar::string($value['kind']),
            'anchorReceiptId' => AuditArtifactScalar::string($value['anchorReceiptId']),
            'partitionId' => self::partition($value['partitionId']),
            'segmentId' => self::segment($value['segmentId']),
            'checkpointId' => AuditArtifactScalar::string($value['checkpointId']),
            'checkpointHash' => self::digest($value['checkpointHash']),
            'checkpointBytesHash' => self::digest($value['checkpointBytesHash']),
            'anchorStoreRef' => AuditArtifactScalar::string($value['anchorStoreRef']),
            'anchorObjectRef' => AuditArtifactScalar::string($value['anchorObjectRef']),
            'anchorObjectVersion' => AuditArtifactScalar::boundedInteger($value['anchorObjectVersion']),
            'previousAnchorReceiptHash' => $previousAnchorReceiptHash,
            'anchoredAt' => AuditArtifactScalar::string($value['anchoredAt']),
            'signedAt' => AuditArtifactScalar::string($value['signedAt']),
            'signatureAlgorithm' => AuditArtifactScalar::string($value['signatureAlgorithm']),
            'signingKeyRef' => AuditArtifactScalar::string($value['signingKeyRef']),
        ];
    }

    private static function partition(#[\SensitiveParameter] mixed $value): string
    {
        $partition = AuditArtifactScalar::string($value);
        if (preg_match('/^(?:system:global|tenant:[0-9a-f]{64})$/D', $partition) !== 1) {
            self::invalid();
        }
        return $partition;
    }

    private static function segment(#[\SensitiveParameter] mixed $value): string
    {
        $segment = AuditArtifactScalar::string($value);
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $segment) !== 1) {
            self::invalid();
        }
        return $segment;
    }

    private static function digest(#[\SensitiveParameter] mixed $value): string
    {
        $digest = AuditArtifactScalar::string($value);
        if (preg_match('/^[0-9a-f]{64}$/D', $digest) !== 1) {
            self::invalid();
        }
        return $digest;
    }

    private static function invalid(): never
    {
        throw new AuditArtifactException(AuditArtifactFailure::ArtifactInvalid);
    }
}
