<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit;

use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Core\Audit\AuditValidationFailure;

final class AuditStringValidator
{
    public static function assertUtf8(#[\SensitiveParameter] string $value): void
    {
        AuditValidationException::assertUtf8($value);
    }

    public static function assertNfc(#[\SensitiveParameter] string $value): void
    {
        AuditValidationException::assertSafeString($value, 0, PHP_INT_MAX);
    }

    public static function assertSafe(#[\SensitiveParameter] string $value, int $minimum = 1, int $maximum = 256): void
    {
        AuditValidationException::assertSafeString($value, $minimum, $maximum);
    }

    public static function assertGrammar(
        #[\SensitiveParameter]
        string $value,
        string $pattern,
        int $maximum = 128,
    ): void {
        self::assertSafe($value, 1, $maximum);
        if (preg_match($pattern, $value) !== 1) {
            throw new AuditValidationException(AuditValidationFailure::GrammarInvalid);
        }
    }
}
