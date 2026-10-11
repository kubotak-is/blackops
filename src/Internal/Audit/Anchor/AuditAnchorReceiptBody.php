<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Anchor;

use BlackOps\Internal\Audit\Artifact\AuditAnchorReceiptCodec;

final class AuditAnchorReceiptBody
{
    /** @return array{body:array<string,mixed>,bytes:string,anchorReceiptHash:string,receiptPreimageHash:string,receiptBytesHash:string} */
    public static function build(
        #[\SensitiveParameter]
        AuditAnchorIntent $intent,
        #[\SensitiveParameter]
        string $signingKeyRef,
        #[\SensitiveParameter]
        string $signedAt,
    ): array {
        try {
            $value = $intent->toArray();
            if (!in_array(
                $value['state'],
                ['externally_accepted', 'receipt_inputs_persisted', 'receipt_persisted', 'acknowledged'],
                strict: true,
            )) {
                throw new \RuntimeException();
            }
            AuditAnchorIntentShape::assertText($signingKeyRef);
            $signedAt = AuditAnchorTime::assertTimestamp($signedAt);
            $acceptedAnchoredAt = AuditAnchorTime::assertTimestamp($value['acceptedAnchoredAt']);
            if (AuditAnchorTime::compare($acceptedAnchoredAt, $signedAt) > 0) {
                throw new \RuntimeException();
            }
            $body = self::unsignedBody($value, $signingKeyRef, $acceptedAnchoredAt, $signedAt);
            $input = AuditAnchorReceiptCodec::signingInput($body);
            return [
                'body' => $body,
                'bytes' => $input['bytes'],
                'anchorReceiptHash' => $input['hash'],
                'receiptPreimageHash' => AuditAnchorCanonical::receiptPreimageHash($body),
                'receiptBytesHash' => AuditAnchorCanonical::receiptBytesHash($body),
            ];
        } catch (\Throwable) {
            throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
        }
    }

    /** @param array<string,mixed> $value */
    public static function assertPersistedHashes(
        #[\SensitiveParameter]
        array $value,
        #[\SensitiveParameter]
        string $signingKeyRef,
        #[\SensitiveParameter]
        string $acceptedAnchoredAt,
        #[\SensitiveParameter]
        string $receiptSignedAt,
    ): void {
        try {
            $body = self::unsignedBody($value, $signingKeyRef, $acceptedAnchoredAt, $receiptSignedAt);
            $receiptPreimageHash = AuditAnchorIntentShape::assertText($value['receiptPreimageHash']);
            $receiptBytesHash = AuditAnchorIntentShape::assertText($value['receiptBytesHash']);
            if (
                !hash_equals(AuditAnchorCanonical::receiptPreimageHash($body), $receiptPreimageHash)
                || !hash_equals(AuditAnchorCanonical::receiptBytesHash($body), $receiptBytesHash)
            ) {
                throw new \RuntimeException();
            }
        } catch (\Throwable) {
            throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
        }
    }

    public static function assertPublicReceiptHash(#[\SensitiveParameter] AuditAnchorIntent $intent): void
    {
        try {
            $value = $intent->toArray();
            if (!in_array($value['state'], ['receipt_persisted', 'acknowledged'], strict: true)) {
                return;
            }
            $signingKeyRef = AuditAnchorIntentShape::assertText($value['receiptSigningKeyRef']);
            $receiptSignedAt = AuditAnchorTime::assertTimestamp($value['receiptSignedAt']);
            $anchorReceiptHash = AuditAnchorIntentShape::assertText($value['anchorReceiptHash']);
            $receipt = self::build($intent, $signingKeyRef, $receiptSignedAt);
            if (!hash_equals($receipt['anchorReceiptHash'], $anchorReceiptHash)) {
                throw new \RuntimeException();
            }
        } catch (\Throwable) {
            throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
        }
    }

    /**
     * @param array<string,mixed> $value
     * @return array{
     *     schemaVersion:int,
     *     kind:string,
     *     anchorReceiptId:mixed,
     *     partitionId:mixed,
     *     segmentId:mixed,
     *     checkpointId:mixed,
     *     checkpointHash:mixed,
     *     checkpointBytesHash:mixed,
     *     anchorStoreRef:mixed,
     *     anchorObjectRef:mixed,
     *     anchorObjectVersion:mixed,
     *     previousAnchorReceiptHash:mixed,
     *     anchoredAt:string,
     *     signedAt:string,
     *     signatureAlgorithm:string,
     *     signingKeyRef:string
     * }
     */
    private static function unsignedBody(
        #[\SensitiveParameter]
        array $value,
        #[\SensitiveParameter]
        string $signingKeyRef,
        #[\SensitiveParameter]
        string $acceptedAnchoredAt,
        #[\SensitiveParameter]
        string $receiptSignedAt,
    ): array {
        return [
            'schemaVersion' => 1,
            'kind' => 'audit_anchor_receipt',
            'anchorReceiptId' => $value['anchorReceiptId'],
            'partitionId' => $value['partitionId'],
            'segmentId' => $value['segmentId'],
            'checkpointId' => $value['checkpointId'],
            'checkpointHash' => $value['checkpointHash'],
            'checkpointBytesHash' => $value['checkpointBytesHash'],
            'anchorStoreRef' => $value['anchorStoreRef'],
            'anchorObjectRef' => $value['anchorObjectRef'],
            'anchorObjectVersion' => $value['acceptedObjectVersion'],
            'previousAnchorReceiptHash' => $value['previousAnchorReceiptHash'],
            'anchoredAt' => $acceptedAnchoredAt,
            'signedAt' => $receiptSignedAt,
            'signatureAlgorithm' => 'ed25519-v1',
            'signingKeyRef' => $signingKeyRef,
        ];
    }
}
