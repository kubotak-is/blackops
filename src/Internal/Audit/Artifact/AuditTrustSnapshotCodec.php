<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Artifact;

final class AuditTrustSnapshotCodec
{
    /** @param array<string,mixed> $snapshot */
    public static function validate(
        #[\SensitiveParameter]
        array $snapshot,
        #[\SensitiveParameter]
        string $verifiedAt,
    ): void {
        $normalized = AuditTrustSnapshotShape::snapshot($snapshot, $verifiedAt);
        if ($normalized['observedAt'] > $verifiedAt || $verifiedAt >= $normalized['validUntil']) {
            self::invalid();
        }
        $body = [
            'ref' => $normalized['ref'],
            'observedAt' => $normalized['observedAt'],
            'validUntil' => $normalized['validUntil'],
            'entries' => $normalized['entries'],
        ];
        $expected = hash('sha256', self::frame(
            'blackops.audit.trust-store-snapshot.v1',
            AuditArtifactCanonical::trustSnapshot($body),
        ));
        if (!hash_equals($expected, $normalized['hash'])) {
            self::invalid();
        }
    }

    /**
     * @param array<string,mixed> $snapshot
     * @return array{keyRef:string,role:string,publicKey:string,publicKeyFingerprint:string,notBefore:string,notAfter:string,revokedAt:?string,status:string}
     */
    public static function key(
        #[\SensitiveParameter]
        array $snapshot,
        #[\SensitiveParameter]
        string $keyRef,
        #[\SensitiveParameter]
        string $expectedRole,
        #[\SensitiveParameter]
        string $verifiedAt,
    ): array {
        return AuditTrustLookup::key($snapshot, $keyRef, $expectedRole, $verifiedAt);
    }

    /** @param array{keyRef:string,role:string,publicKey:string,publicKeyFingerprint:string,notBefore:string,notAfter:string,revokedAt:?string,status:string} $entry */
    public static function assertSignedAt(
        #[\SensitiveParameter]
        array $entry,
        #[\SensitiveParameter]
        string $signedAt,
        #[\SensitiveParameter]
        string $verifiedAt,
    ): void {
        AuditTrustLookup::assertSignedAt($entry, $signedAt, $verifiedAt);
    }

    private static function frame(#[\SensitiveParameter] string $domain, #[\SensitiveParameter] string $bytes): string
    {
        return 's:' . strlen($domain) . ':' . $domain . 'b:' . strlen($bytes) . ':' . $bytes;
    }

    private static function invalid(): never
    {
        throw new AuditArtifactException(AuditArtifactFailure::TrustInvalid);
    }
}
