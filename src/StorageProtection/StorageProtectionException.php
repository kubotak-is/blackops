<?php

declare(strict_types=1);

namespace BlackOps\StorageProtection;

use BlackOps\Core\Attribute\PublicApi;
use RuntimeException;

#[PublicApi]
final class StorageProtectionException extends RuntimeException
{
    public static function failure(): self
    {
        return new self('Protected storage data is unavailable.');
    }
}
