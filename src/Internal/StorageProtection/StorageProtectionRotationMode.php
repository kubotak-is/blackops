<?php

declare(strict_types=1);

namespace BlackOps\Internal\StorageProtection;

enum StorageProtectionRotationMode
{
    case Plan;
    case Confirmed;

    public function writes(): bool
    {
        return $this === self::Confirmed;
    }
}
