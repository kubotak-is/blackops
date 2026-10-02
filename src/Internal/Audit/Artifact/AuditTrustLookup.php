<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Artifact;

use BlackOps\Core\Audit\AuditRecordTime;

final class AuditTrustLookup
{
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
        AuditTrustSnapshotCodec::validate($snapshot, $verifiedAt);
        $normalized = AuditTrustSnapshotShape::snapshot($snapshot, $verifiedAt);
        $keyRef = AuditTrustScalar::string($keyRef, 256);
        $expectedRole = AuditTrustScalar::string($expectedRole, 16);
        if (!in_array($expectedRole, ['checkpoint', 'anchor'], strict: true)) {
            self::invalid();
        }
        foreach ($normalized['entries'] as $entry) {
            if ($entry['keyRef'] !== $keyRef) {
                continue;
            }
            if ($entry['role'] !== $expectedRole) {
                self::invalid();
            }
            self::assertCurrent($entry, $verifiedAt);
            return [
                'keyRef' => $entry['keyRef'],
                'role' => $entry['role'],
                'publicKey' => $entry['publicKey'],
                'publicKeyFingerprint' => $entry['publicKeyFingerprint'],
                'notBefore' => $entry['notBefore'],
                'notAfter' => $entry['notAfter'],
                'revokedAt' => $entry['revokedAt'],
                'status' => $entry['status'],
            ];
        }
        self::invalid();
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
        try {
            AuditRecordTime::assertTimestamp($signedAt);
            AuditRecordTime::assertTimestamp($verifiedAt);
        } catch (\Throwable) {
            self::invalid();
        }
        $normalized = AuditTrustEntryShape::entry($entry, $verifiedAt, $verifiedAt);
        AuditTrustEntryRelation::assert($normalized, $verifiedAt, $verifiedAt);
        self::assertCurrent($normalized, $verifiedAt);
        if ($normalized['notBefore'] > $signedAt || $signedAt > $verifiedAt) {
            self::invalid();
        }
    }

    /** @param array{keyRef:string,role:string,publicKey:string,publicKeyFingerprint:string,notBefore:string,notAfter:string,revokedAt:?string,status:string} $entry */
    private static function assertCurrent(
        #[\SensitiveParameter]
        array $entry,
        #[\SensitiveParameter]
        string $verifiedAt,
    ): void {
        if (
            $entry['status'] !== 'active'
            || $entry['revokedAt'] !== null
            || $entry['notBefore'] > $verifiedAt
            || $verifiedAt >= $entry['notAfter']
        ) {
            self::invalid();
        }
    }

    private static function invalid(): never
    {
        throw new AuditArtifactException(AuditArtifactFailure::TrustInvalid);
    }
}
