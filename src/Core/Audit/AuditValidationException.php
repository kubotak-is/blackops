<?php

declare(strict_types=1);

namespace BlackOps\Core\Audit;

use BlackOps\Core\Attribute\PublicApi;
use RuntimeException;

#[PublicApi]
final class AuditValidationException extends RuntimeException
{
    public const int MAX_SAFE_INTEGER = 9_007_199_254_740_991;

    public function __construct(
        private readonly AuditValidationFailure $failure,
    ) {
        parent::__construct($failure->value);
    }

    public function failure(): AuditValidationFailure
    {
        return $this->failure;
    }

    public function code(): string
    {
        return $this->failure->value;
    }

    public static function assertUtf8(#[\SensitiveParameter] string $value): void
    {
        if (preg_match('//u', $value) !== 1) {
            throw new self(AuditValidationFailure::Utf8Invalid);
        }
    }

    public static function assertSafeString(
        #[\SensitiveParameter]
        string $value,
        int $minimum = 1,
        int $maximum = 256,
    ): void {
        self::assertUtf8($value);
        if (preg_match('/[\p{Cc}\p{Cf}]/u', $value) === 1) {
            throw new self(AuditValidationFailure::ControlInvalid);
        }
        if (class_exists('Normalizer')) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_C);
            if ($normalized === false || $normalized !== $value) {
                throw new self(AuditValidationFailure::NfcInvalid);
            }
        }
        $length = strlen($value);
        if ($length < $minimum || $length > $maximum) {
            throw new self(AuditValidationFailure::ByteBoundInvalid);
        }
    }

    public static function assertGrammarString(
        #[\SensitiveParameter]
        string $value,
        string $pattern,
        int $maximum = 128,
    ): void {
        self::assertSafeString($value, 1, $maximum);
        if (preg_match($pattern, $value) !== 1) {
            throw new self(AuditValidationFailure::GrammarInvalid);
        }
    }
}
