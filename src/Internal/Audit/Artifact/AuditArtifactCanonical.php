<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Artifact;

use BlackOps\Internal\Audit\AuditJcsCodec;

final class AuditArtifactCanonical
{
    /** @param array<string,mixed> $value */
    public static function artifact(#[\SensitiveParameter] array $value): string
    {
        try {
            $bytes = new AuditJcsCodec()->canonicalJson($value);
        } catch (\Throwable) {
            self::invalid();
        }
        if ($bytes === '' || strlen($bytes) > 1_048_575) {
            self::invalid();
        }
        return $bytes;
    }

    /** @param array<string,mixed> $value */
    public static function trustSnapshot(#[\SensitiveParameter] array $value): string
    {
        try {
            $bytes = new AuditJcsCodec()->canonicalJson($value);
        } catch (\Throwable) {
            self::trustInvalid();
        }
        if ($bytes === '' || strlen($bytes) > 1_048_576) {
            self::trustInvalid();
        }
        return $bytes;
    }

    /** @return array<string,mixed> */
    public static function decode(#[\SensitiveParameter] string $bytes): array
    {
        AuditArtifactWirePreflight::assertFlatObject($bytes);
        try {
            $jcs = new AuditJcsCodec();
            $decoded = $jcs->decode($bytes);
            $value = $jcs->toNative($decoded);
        } catch (\Throwable) {
            self::invalid();
        }
        if (!is_array($value) || array_is_list($value)) {
            self::invalid();
        }
        /** @var array<string,mixed> $value */
        return $value;
    }

    private static function invalid(): never
    {
        throw new AuditArtifactException(AuditArtifactFailure::ArtifactInvalid);
    }

    private static function trustInvalid(): never
    {
        throw new AuditArtifactException(AuditArtifactFailure::TrustInvalid);
    }
}
