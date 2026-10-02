<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Artifact;

use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\AuditStringValidator;

final class AuditArtifactScalar
{
    /**
     * @param array<array-key,mixed> $value
     * @param list<string> $keys
     * @return array<string,mixed>
     */
    public static function closed(#[\SensitiveParameter] array $value, #[\SensitiveParameter] array $keys): array
    {
        if (array_is_list($value) || count($value) !== count($keys)) {
            self::invalid();
        }
        foreach (array_keys($value) as $key) {
            if (is_string($key)) {
                continue;
            }
            self::invalid();
        }
        if (array_diff(array_keys($value), $keys) !== [] || array_diff($keys, array_keys($value)) !== []) {
            self::invalid();
        }
        /** @var array<string,mixed> $value */
        return $value;
    }

    public static function string(#[\SensitiveParameter] mixed $value): string
    {
        if (!is_string($value) || strlen($value) < 1 || strlen($value) > 256) {
            self::invalid();
        }
        try {
            AuditStringValidator::assertSafe($value);
        } catch (\Throwable) {
            self::invalid();
        }
        return $value;
    }

    public static function nullableString(#[\SensitiveParameter] mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        return self::string($value);
    }

    public static function boundedInteger(#[\SensitiveParameter] mixed $value): int
    {
        if (!is_int($value) || $value < 1 || $value > AuditJcsCodec::MAX_SAFE_INTEGER) {
            self::invalid();
        }
        return $value;
    }

    private static function invalid(): never
    {
        throw new AuditArtifactException(AuditArtifactFailure::ArtifactInvalid);
    }
}
