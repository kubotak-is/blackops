<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Anchor;

use BlackOps\Core\Audit\AuditRecordTime;

final class AuditAnchorTime
{
    public static function assertTimestamp(#[\SensitiveParameter] mixed $value): string
    {
        if (!is_string($value)) {
            throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
        }
        try {
            AuditRecordTime::assertTimestamp($value);
        } catch (\Throwable) {
            throw new AuditAnchorException(AuditAnchorFailure::IntegrityInvalid);
        }
        return $value;
    }

    public static function assertTransitionTimestamp(#[\SensitiveParameter] mixed $value): string
    {
        try {
            return self::assertTimestamp($value);
        } catch (\Throwable) {
            throw new AuditAnchorException(AuditAnchorFailure::TransitionConflict);
        }
    }

    public static function addSeconds(#[\SensitiveParameter] string $value, int $seconds): string
    {
        self::assertTimestamp($value);
        if ($seconds < 1 || $seconds > 86_400) {
            throw new AuditAnchorException(AuditAnchorFailure::ResourceUnavailable);
        }
        try {
            $time = \DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s.u\\Z', $value, new \DateTimeZone('UTC'));
            if ($time === false) {
                throw new \RuntimeException();
            }
            $result = $time->modify('+' . $seconds . ' seconds');
            $formatted = $result->format('Y-m-d\\TH:i:s.u\\Z');
            try {
                self::assertTimestamp($formatted);
            } catch (\Throwable) {
                throw new AuditAnchorException(AuditAnchorFailure::ResourceUnavailable);
            }
            return $formatted;
        } catch (AuditAnchorException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new AuditAnchorException(AuditAnchorFailure::ResourceUnavailable);
        }
    }

    public static function compare(#[\SensitiveParameter] string $left, #[\SensitiveParameter] string $right): int
    {
        self::assertTimestamp($left);
        self::assertTimestamp($right);
        return $left <=> $right;
    }
}
