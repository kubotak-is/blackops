<?php

declare(strict_types=1);

namespace BlackOps\Internal\StorageProtection;

interface NonceSource
{
    public function generate(): string;
}
