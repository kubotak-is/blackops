<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit;

final class AuditLengthPrefix
{
    public static function encode(string $value): string
    {
        return pack('N', strlen($value)) . $value;
    }
}
