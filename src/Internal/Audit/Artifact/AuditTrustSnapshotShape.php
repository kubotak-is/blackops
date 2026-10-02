<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Artifact;

use BlackOps\Core\Audit\AuditRecordTime;

final class AuditTrustSnapshotShape
{
    /**
     * @param array<string,mixed> $value
     * @return array{ref:string,hash:string,observedAt:string,validUntil:string,entries:list<array{keyRef:string,role:string,publicKey:string,publicKeyFingerprint:string,notBefore:string,notAfter:string,revokedAt:?string,status:string}>}
     */
    public static function snapshot(
        #[\SensitiveParameter]
        array $value,
        #[\SensitiveParameter]
        string $verifiedAt,
    ): array {
        $value = AuditTrustScalar::closed($value, ['ref', 'hash', 'observedAt', 'validUntil', 'entries']);
        $ref = AuditTrustScalar::string($value['ref'], 256);
        $hash = AuditTrustScalar::string($value['hash'], 64);
        $observedAt = AuditTrustScalar::string($value['observedAt'], 27);
        $validUntil = AuditTrustScalar::string($value['validUntil'], 27);
        $entries = $value['entries'];
        if (!is_array($entries) || !array_is_list($entries) || count($entries) < 1 || count($entries) > 256) {
            self::invalid();
        }
        try {
            AuditRecordTime::assertTimestamp($verifiedAt);
            AuditRecordTime::assertTimestamp($observedAt);
            AuditRecordTime::assertTimestamp($validUntil);
        } catch (\Throwable) {
            self::invalid();
        }
        if (preg_match('/^[0-9a-f]{64}$/D', $hash) !== 1 || $observedAt >= $validUntil) {
            self::invalid();
        }
        $normalizedEntries = [];
        $previousKeyRef = null;
        $keyRefs = [];
        $fingerprints = [];
        foreach ($entries as $entry) {
            $normalized = AuditTrustEntryShape::entry($entry, $observedAt, $verifiedAt);
            AuditTrustEntryRelation::assert($normalized, $observedAt, $verifiedAt);
            if (
                $previousKeyRef !== null && strcmp($previousKeyRef, $normalized['keyRef']) >= 0
                || array_key_exists($normalized['keyRef'], $keyRefs)
                || array_key_exists($normalized['publicKeyFingerprint'], $fingerprints)
            ) {
                self::invalid();
            }
            $normalizedEntries[] = $normalized;
            $previousKeyRef = $normalized['keyRef'];
            $keyRefs[$normalized['keyRef']] = true;
            $fingerprints[$normalized['publicKeyFingerprint']] = true;
        }
        /** @var list<array{keyRef:string,role:string,publicKey:string,publicKeyFingerprint:string,notBefore:string,notAfter:string,revokedAt:?string,status:string}> $normalizedEntries */
        return [
            'ref' => $ref,
            'hash' => $hash,
            'observedAt' => $observedAt,
            'validUntil' => $validUntil,
            'entries' => $normalizedEntries,
        ];
    }

    private static function invalid(): never
    {
        throw new AuditArtifactException(AuditArtifactFailure::TrustInvalid);
    }
}
