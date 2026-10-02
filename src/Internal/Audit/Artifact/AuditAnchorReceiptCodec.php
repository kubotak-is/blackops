<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Artifact;

final class AuditAnchorReceiptCodec
{
    /**
     * @param array<string,mixed> $unsigned
     * @return array{bytes:string,hash:string,preimage:string}
     */
    public static function signingInput(#[\SensitiveParameter] array $unsigned): array
    {
        $normalized = AuditArtifactShape::anchorUnsigned($unsigned);
        AuditArtifactRelation::anchor($normalized);
        $bytes = AuditArtifactCanonical::artifact($normalized);
        $hash = hash('sha256', self::frame('blackops.audit.anchor.hash.v1', $bytes));
        return [
            'bytes' => $bytes,
            'hash' => $hash,
            'preimage' => self::frame('blackops.audit.anchor.signature.v1', sodium_hex2bin($hash)),
        ];
    }

    /** @param array<string,mixed> $unsigned */
    public static function complete(
        #[\SensitiveParameter]
        array $unsigned,
        #[\SensitiveParameter]
        string $rawSignature,
    ): string {
        $input = self::signingInput($unsigned);
        if (strlen($rawSignature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            self::invalid();
        }
        $complete = AuditArtifactShape::anchorUnsigned($unsigned);
        $complete['anchorReceiptHash'] = $input['hash'];
        $complete['signature'] = sodium_bin2base64($rawSignature, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        $bytes = AuditArtifactCanonical::artifact($complete);
        self::decode($bytes);
        return $bytes;
    }

    /** @return array{schemaVersion:int,kind:string,anchorReceiptId:string,partitionId:string,segmentId:string,checkpointId:string,checkpointHash:string,checkpointBytesHash:string,anchorStoreRef:string,anchorObjectRef:string,anchorObjectVersion:int,previousAnchorReceiptHash:?string,anchoredAt:string,signedAt:string,signatureAlgorithm:string,signingKeyRef:string,anchorReceiptHash:string,signature:string} */
    public static function decode(#[\SensitiveParameter] string $completeBytes): array
    {
        $value = AuditArtifactCanonical::decode($completeBytes);
        $complete = AuditArtifactShape::anchorComplete($value);
        $unsigned = $complete;
        unset($unsigned['anchorReceiptHash'], $unsigned['signature']);
        /** @var array{schemaVersion:int,kind:string,anchorReceiptId:string,partitionId:string,segmentId:string,checkpointId:string,checkpointHash:string,checkpointBytesHash:string,anchorStoreRef:string,anchorObjectRef:string,anchorObjectVersion:int,previousAnchorReceiptHash:?string,anchoredAt:string,signedAt:string,signatureAlgorithm:string,signingKeyRef:string} $unsigned */
        AuditArtifactRelation::anchor($unsigned);
        AuditArtifactIntegrity::anchor($complete, $completeBytes);
        return $complete;
    }

    /** @param array<string,mixed> $unsigned */
    public static function assertCheckpointBinding(
        #[\SensitiveParameter]
        array $unsigned,
        #[\SensitiveParameter]
        string $checkpointBytes,
    ): void {
        $normalized = AuditArtifactShape::anchorUnsigned($unsigned);
        AuditArtifactRelation::anchor($normalized);
        $checkpoint = AuditCheckpointCodec::decode($checkpointBytes);
        AuditAnchorBinding::assert($normalized, $checkpoint, $checkpointBytes);
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
