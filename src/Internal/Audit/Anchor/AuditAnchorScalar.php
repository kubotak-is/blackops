<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Anchor;

use BlackOps\Internal\Audit\AuditStringValidator;

final class AuditAnchorScalar
{
    public static function text(#[\SensitiveParameter] mixed $value, int $minimum = 1, int $maximum = 256): string
    {
        if (!is_string($value)) {
            throw new \RuntimeException();
        }
        AuditStringValidator::assertSafe($value, $minimum, $maximum);
        return $value;
    }

    public static function hash(#[\SensitiveParameter] mixed $value, string $mode = 'required'): ?string
    {
        if (!in_array($mode, ['required', 'nullable'], strict: true)) {
            throw new \RuntimeException();
        }
        if ($mode === 'nullable' && $value === null) {
            return null;
        }
        if (!is_string($value) || preg_match('/^[a-f0-9]{64}$/D', $value) !== 1) {
            throw new \RuntimeException();
        }
        return $value;
    }

    public static function integer(#[\SensitiveParameter] mixed $value, int $minimum, int $maximum): int
    {
        if (!is_int($value) || $value < $minimum || $value > $maximum) {
            throw new \RuntimeException();
        }
        return $value;
    }

    public static function nullableInteger(#[\SensitiveParameter] mixed $value, int $minimum, int $maximum): ?int
    {
        return $value === null ? null : self::integer($value, $minimum, $maximum);
    }

    public static function nullableTimestamp(#[\SensitiveParameter] mixed $value): ?string
    {
        return $value === null ? null : AuditAnchorTime::assertTimestamp($value);
    }

    /** @param array<array-key,mixed> $allowed */
    public static function nullableAllowed(
        #[\SensitiveParameter]
        mixed $value,
        #[\SensitiveParameter]
        array $allowed,
    ): void {
        if ($value !== null && (!is_string($value) || !in_array($value, $allowed, strict: true))) {
            throw new \RuntimeException();
        }
    }

    public static function nullableText(#[\SensitiveParameter] mixed $value): ?string
    {
        return $value === null ? null : self::text($value);
    }
}
