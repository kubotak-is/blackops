<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Artifact;

final class AuditArtifactIntegrity
{
    /** @param array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string,checkpointHash:string,signature:string} $value */
    public static function checkpoint(#[\SensitiveParameter] array $value, #[\SensitiveParameter] string $bytes): void
    {
        $unsigned = $value;
        unset($unsigned['checkpointHash'], $unsigned['signature']);
        if (AuditArtifactCanonical::artifact($value) !== $bytes) {
            self::invalid();
        }
        $expected = hash('sha256', self::frame(
            'blackops.audit.checkpoint.hash.v1',
            AuditArtifactCanonical::artifact($unsigned),
        ));
        if (!hash_equals($expected, $value['checkpointHash'])) {
            self::invalid();
        }
        self::signature($value['signature']);
    }

    /** @param array{schemaVersion:int,kind:string,anchorReceiptId:string,partitionId:string,segmentId:string,checkpointId:string,checkpointHash:string,checkpointBytesHash:string,anchorStoreRef:string,anchorObjectRef:string,anchorObjectVersion:int,previousAnchorReceiptHash:?string,anchoredAt:string,signedAt:string,signatureAlgorithm:string,signingKeyRef:string,anchorReceiptHash:string,signature:string} $value */
    public static function anchor(#[\SensitiveParameter] array $value, #[\SensitiveParameter] string $bytes): void
    {
        $unsigned = $value;
        unset($unsigned['anchorReceiptHash'], $unsigned['signature']);
        if (AuditArtifactCanonical::artifact($value) !== $bytes) {
            self::invalid();
        }
        $expected = hash('sha256', self::frame(
            'blackops.audit.anchor.hash.v1',
            AuditArtifactCanonical::artifact($unsigned),
        ));
        if (!hash_equals($expected, $value['anchorReceiptHash'])) {
            self::invalid();
        }
        self::signature($value['signature']);
    }

    private static function signature(#[\SensitiveParameter] string $encoded): void
    {
        try {
            $decoded = sodium_base642bin($encoded, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
            if (
                strlen($decoded) !== SODIUM_CRYPTO_SIGN_BYTES
                || sodium_bin2base64($decoded, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING) !== $encoded
            ) {
                self::invalid();
            }
        } catch (\Throwable) {
            self::invalid();
        }
    }

    private static function frame(#[\SensitiveParameter] string $domain, #[\SensitiveParameter] string $bytes): string
    {
        return 's:' . strlen($domain) . ':' . $domain . 'b:' . strlen($bytes) . ':' . $bytes;
    }

    private static function invalid(): never
    {
        throw new AuditArtifactException(AuditArtifactFailure::ArtifactInvalid);
    }
}
