<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Artifact;

use BlackOps\Internal\Audit\AuditStringValidator;

final class AuditTrustScalar
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

    public static function string(#[\SensitiveParameter] mixed $value, #[\SensitiveParameter] int $maximum): string
    {
        if (!is_string($value) || strlen($value) < 1 || strlen($value) > $maximum) {
            self::invalid();
        }
        try {
            AuditStringValidator::assertSafe($value, 1, $maximum);
        } catch (\Throwable) {
            self::invalid();
        }
        return $value;
    }

    public static function publicKey(#[\SensitiveParameter] mixed $value): string
    {
        if (!is_string($value) || strlen($value) !== 43) {
            self::invalid();
        }
        try {
            $decoded = sodium_base642bin($value, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
            if (
                strlen($decoded) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
                || sodium_bin2base64($decoded, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING) !== $value
            ) {
                self::invalid();
            }
            return $decoded;
        } catch (\Throwable) {
            self::invalid();
        }
    }

    private static function invalid(): never
    {
        throw new AuditArtifactException(AuditArtifactFailure::TrustInvalid);
    }
}
