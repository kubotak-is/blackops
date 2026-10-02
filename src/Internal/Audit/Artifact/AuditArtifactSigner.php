<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Artifact;

final class AuditArtifactSigner
{
    /**
     * @param array<string,mixed> $unsigned
     * @param array<string,mixed> $trustedSnapshot
     */
    public static function checkpoint(
        #[\SensitiveParameter]
        array $unsigned,
        #[\SensitiveParameter]
        string $privateSeed,
        #[\SensitiveParameter]
        array $trustedSnapshot,
        #[\SensitiveParameter]
        string $trustedNow,
    ): string {
        $input = AuditCheckpointCodec::signingInput($unsigned);
        $normalized = AuditArtifactShape::checkpointUnsigned($unsigned);
        $entry = AuditTrustSnapshotCodec::key(
            $trustedSnapshot,
            $normalized['signingKeyRef'],
            'checkpoint',
            $trustedNow,
        );
        AuditTrustSnapshotCodec::assertSignedAt($entry, $normalized['signedAt'], $trustedNow);
        self::assertSeedLength($privateSeed);
        return AuditCheckpointCodec::complete($normalized, self::sign($input['preimage'], $privateSeed, $entry));
    }

    /**
     * @param array<string,mixed> $unsigned
     * @param array<string,mixed> $trustedSnapshot
     */
    public static function anchorReceipt(
        #[\SensitiveParameter]
        array $unsigned,
        #[\SensitiveParameter]
        string $checkpointBytes,
        #[\SensitiveParameter]
        string $privateSeed,
        #[\SensitiveParameter]
        array $trustedSnapshot,
        #[\SensitiveParameter]
        string $trustedNow,
    ): string {
        $input = AuditAnchorReceiptCodec::signingInput($unsigned);
        $normalized = AuditArtifactShape::anchorUnsigned($unsigned);
        AuditAnchorReceiptCodec::assertCheckpointBinding($normalized, $checkpointBytes);
        AuditArtifactVerifier::checkpoint($checkpointBytes, $trustedSnapshot, $trustedNow);
        $entry = AuditTrustSnapshotCodec::key($trustedSnapshot, $normalized['signingKeyRef'], 'anchor', $trustedNow);
        AuditTrustSnapshotCodec::assertSignedAt($entry, $normalized['signedAt'], $trustedNow);
        self::assertSeedLength($privateSeed);
        return AuditAnchorReceiptCodec::complete($normalized, self::sign($input['preimage'], $privateSeed, $entry));
    }

    private static function assertSeedLength(#[\SensitiveParameter] string $privateSeed): void
    {
        if (strlen($privateSeed) !== SODIUM_CRYPTO_SIGN_SEEDBYTES) {
            throw new AuditArtifactException(AuditArtifactFailure::SigningUnavailable);
        }
    }

    /** @param array{keyRef:string,role:string,publicKey:string,publicKeyFingerprint:string,notBefore:string,notAfter:string,revokedAt:?string,status:string} $entry */
    private static function sign(
        #[\SensitiveParameter]
        string $preimage,
        #[\SensitiveParameter]
        string $privateSeed,
        #[\SensitiveParameter]
        array $entry,
    ): string {
        $keyPair = '';
        $secretKey = '';
        try {
            $expectedPublicKey = sodium_base642bin($entry['publicKey'], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
            $keyPair = sodium_crypto_sign_seed_keypair($privateSeed);
            $secretKey = sodium_crypto_sign_secretkey($keyPair);
            $publicKey = sodium_crypto_sign_publickey($keyPair);
            if (!hash_equals($expectedPublicKey, $publicKey)) {
                throw new AuditArtifactException(AuditArtifactFailure::SigningUnavailable);
            }
            $signature = sodium_crypto_sign_detached($preimage, $secretKey);
            if (strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
                throw new AuditArtifactException(AuditArtifactFailure::SigningUnavailable);
            }
            return $signature;
        } catch (\Throwable) {
            throw new AuditArtifactException(AuditArtifactFailure::SigningUnavailable);
        } finally {
            if ($secretKey !== '') {
                sodium_memzero($secretKey);
            }
            if ($keyPair !== '') {
                sodium_memzero($keyPair);
            }
        }
    }
}
