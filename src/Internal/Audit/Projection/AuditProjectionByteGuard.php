<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Projection;

use BlackOps\Core\Audit\AuditProjectionException;

final class AuditProjectionByteGuard
{
    public const int MAX_BYTES = 65_536;

    public function guard(string $bytes): string
    {
        $length = strlen($bytes);
        if (
            $length < 2
            || $length > self::MAX_BYTES
            || !str_ends_with($bytes, "\n")
            || substr_count(haystack: $bytes, needle: "\n") !== 1
            || str_contains($bytes, "\r")
            || str_starts_with($bytes, "\xEF\xBB\xBF")
        ) {
            throw new AuditProjectionException();
        }
        return $bytes;
    }
}
