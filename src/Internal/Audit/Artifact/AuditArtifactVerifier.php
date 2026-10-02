<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Artifact;

final class AuditArtifactVerifier
{
    /** @param array<string,mixed> $trustedSnapshot */
    public static function checkpoint(
        #[\SensitiveParameter]
        string $checkpointBytes,
        #[\SensitiveParameter]
        array $trustedSnapshot,
        #[\SensitiveParameter]
        string $verifiedAt,
    ): void {
        $checkpoint = AuditCheckpointCodec::decode($checkpointBytes);
        $entry = AuditTrustSnapshotCodec::key(
            $trustedSnapshot,
            $checkpoint['signingKeyRef'],
            'checkpoint',
            $verifiedAt,
        );
        self::verifyDetached($checkpoint, $entry, 'blackops.audit.checkpoint.signature.v1', 'checkpointHash');
        AuditTrustSnapshotCodec::assertSignedAt($entry, $checkpoint['signedAt'], $verifiedAt);
    }

    /** @param array<string,mixed> $trustedSnapshot */
    public static function anchorReceipt(
        #[\SensitiveParameter]
        string $receiptBytes,
        #[\SensitiveParameter]
        string $checkpointBytes,
        #[\SensitiveParameter]
        array $trustedSnapshot,
        #[\SensitiveParameter]
        string $verifiedAt,
    ): void {
        $receipt = AuditAnchorReceiptCodec::decode($receiptBytes);
        AuditCheckpointCodec::decode($checkpointBytes);
        $unsigned = $receipt;
        unset($unsigned['anchorReceiptHash'], $unsigned['signature']);
        AuditAnchorReceiptCodec::assertCheckpointBinding($unsigned, $checkpointBytes);
        self::checkpoint($checkpointBytes, $trustedSnapshot, $verifiedAt);
        $entry = AuditTrustSnapshotCodec::key($trustedSnapshot, $receipt['signingKeyRef'], 'anchor', $verifiedAt);
        self::verifyDetached($receipt, $entry, 'blackops.audit.anchor.signature.v1', 'anchorReceiptHash');
        AuditTrustSnapshotCodec::assertSignedAt($entry, $receipt['signedAt'], $verifiedAt);
    }

    /**
     * @param array<string,mixed> $artifact
     * @param array{keyRef:string,role:string,publicKey:string,publicKeyFingerprint:string,notBefore:string,notAfter:string,revokedAt:?string,status:string} $entry
     */
    private static function verifyDetached(
        #[\SensitiveParameter]
        array $artifact,
        #[\SensitiveParameter]
        array $entry,
        #[\SensitiveParameter]
        string $domain,
        #[\SensitiveParameter]
        string $hashField,
    ): void {
        try {
            $digestHex = $artifact[$hashField];
            $encodedSignature = $artifact['signature'];
            if (!is_string($digestHex) || !is_string($encodedSignature)) {
                throw new \RuntimeException();
            }
            $digest = hex2bin($digestHex);
            $signature = sodium_base642bin($encodedSignature, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
            $publicKey = sodium_base642bin($entry['publicKey'], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
            if (
                $digest === false
                || strlen($digest) !== 32
                || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
                || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            ) {
                throw new \RuntimeException();
            }
            $preimage = 's:' . strlen($domain) . ':' . $domain . 'b:' . strlen($digest) . ':' . $digest;
            if (!sodium_crypto_sign_verify_detached($signature, $preimage, $publicKey)) {
                throw new \RuntimeException();
            }
        } catch (\Throwable) {
            throw new AuditArtifactException(AuditArtifactFailure::SignatureInvalid);
        }
    }
}
