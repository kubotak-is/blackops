<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Artifact;

final class AuditTrustEntryShape
{
    /** @return array{keyRef:string,role:string,publicKey:string,publicKeyFingerprint:string,notBefore:string,notAfter:string,revokedAt:?string,status:string} */
    public static function entry(
        #[\SensitiveParameter]
        mixed $value,
        #[\SensitiveParameter]
        string $observedAt,
        #[\SensitiveParameter]
        string $verifiedAt,
    ): array {
        if (!is_array($value)) {
            self::invalid();
        }
        /** @var array<string,mixed> $value */
        $value = AuditTrustScalar::closed($value, [
            'keyRef',
            'role',
            'publicKey',
            'publicKeyFingerprint',
            'notBefore',
            'notAfter',
            'revokedAt',
            'status',
        ]);
        $keyRef = AuditTrustScalar::string($value['keyRef'], 256);
        $role = AuditTrustScalar::string($value['role'], 16);
        $encodedPublicKey = AuditTrustScalar::string($value['publicKey'], 43);
        $rawPublicKey = AuditTrustScalar::publicKey($encodedPublicKey);
        $publicKeyFingerprint = AuditTrustScalar::string($value['publicKeyFingerprint'], 64);
        $notBefore = AuditTrustScalar::string($value['notBefore'], 27);
        $notAfter = AuditTrustScalar::string($value['notAfter'], 27);
        $revokedAt = $value['revokedAt'] === null ? null : AuditTrustScalar::string($value['revokedAt'], 27);
        $status = AuditTrustScalar::string($value['status'], 16);
        if (
            !in_array($role, ['checkpoint', 'anchor', 'export'], strict: true)
            || !in_array($status, ['active', 'revoked', 'expired'], strict: true)
            || preg_match('/^[0-9a-f]{64}$/D', $publicKeyFingerprint) !== 1
            || !hash_equals(hash('sha256', $rawPublicKey), $publicKeyFingerprint)
        ) {
            self::invalid();
        }
        return [
            'keyRef' => $keyRef,
            'role' => $role,
            'publicKey' => $encodedPublicKey,
            'publicKeyFingerprint' => $publicKeyFingerprint,
            'notBefore' => $notBefore,
            'notAfter' => $notAfter,
            'revokedAt' => $revokedAt,
            'status' => $status,
        ];
    }

    private static function invalid(): never
    {
        throw new AuditArtifactException(AuditArtifactFailure::TrustInvalid);
    }
}
