<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Artifact;

use BlackOps\Core\Audit\AuditRecordTime;

final class AuditTrustEntryRelation
{
    /** @param array{keyRef:string,role:string,publicKey:string,publicKeyFingerprint:string,notBefore:string,notAfter:string,revokedAt:?string,status:string} $entry */
    public static function assert(
        #[\SensitiveParameter]
        array $entry,
        #[\SensitiveParameter]
        string $observedAt,
        #[\SensitiveParameter]
        string $verifiedAt,
    ): void {
        try {
            AuditRecordTime::assertTimestamp($observedAt);
            AuditRecordTime::assertTimestamp($verifiedAt);
            AuditRecordTime::assertTimestamp($entry['notBefore']);
            AuditRecordTime::assertTimestamp($entry['notAfter']);
            if ($entry['revokedAt'] !== null) {
                AuditRecordTime::assertTimestamp($entry['revokedAt']);
            }
        } catch (\Throwable) {
            self::invalid();
        }
        if (
            $entry['notBefore'] >= $entry['notAfter']
            || $entry['status'] === 'active' && $entry['revokedAt'] !== null
            || $entry['status'] === 'revoked' && ($entry['revokedAt'] === null || $entry['revokedAt'] > $observedAt)
            || $entry['status'] === 'expired' && ($entry['revokedAt'] !== null || $entry['notAfter'] > $verifiedAt)
        ) {
            self::invalid();
        }
    }

    private static function invalid(): never
    {
        throw new AuditArtifactException(AuditArtifactFailure::TrustInvalid);
    }
}
