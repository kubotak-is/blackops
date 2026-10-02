<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Artifact;

final class AuditCheckpointCodec
{
    /**
     * @param array<string,mixed> $unsigned
     * @return array{bytes:string,hash:string,preimage:string}
     */
    public static function signingInput(#[\SensitiveParameter] array $unsigned): array
    {
        $normalized = AuditArtifactShape::checkpointUnsigned($unsigned);
        AuditArtifactRelation::checkpoint($normalized);
        $bytes = AuditArtifactCanonical::artifact($normalized);
        $hash = hash('sha256', self::frame('blackops.audit.checkpoint.hash.v1', $bytes));
        return [
            'bytes' => $bytes,
            'hash' => $hash,
            'preimage' => self::frame('blackops.audit.checkpoint.signature.v1', sodium_hex2bin($hash)),
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
        $complete = AuditArtifactShape::checkpointUnsigned($unsigned);
        $complete['checkpointHash'] = $input['hash'];
        $complete['signature'] = sodium_bin2base64($rawSignature, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        $bytes = AuditArtifactCanonical::artifact($complete);
        self::decode($bytes);
        return $bytes;
    }

    /** @return array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string,checkpointHash:string,signature:string} */
    public static function decode(#[\SensitiveParameter] string $completeBytes): array
    {
        $value = AuditArtifactCanonical::decode($completeBytes);
        $complete = AuditArtifactShape::checkpointComplete($value);
        $unsigned = $complete;
        unset($unsigned['checkpointHash'], $unsigned['signature']);
        /** @var array{schemaVersion:int,kind:string,checkpointId:string,partitionId:string,segmentId:string,firstSequence:int,lastSequence:int,recordCount:int,firstRecordHash:string,chainHeadHash:string,previousCheckpointHash:?string,sealedAt:string,signedAt:string,hashSuite:string,signatureAlgorithm:string,signingKeyRef:string} $unsigned */
        AuditArtifactRelation::checkpoint($unsigned);
        AuditArtifactIntegrity::checkpoint($complete, $completeBytes);
        return $complete;
    }

    public static function bytesHash(#[\SensitiveParameter] string $completeBytes): string
    {
        self::decode($completeBytes);
        return hash('sha256', self::frame('blackops.audit.anchor.checkpoint-bytes.v1', $completeBytes));
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
