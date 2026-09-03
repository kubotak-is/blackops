<?php

declare(strict_types=1);

namespace BlackOps\Audit;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\StorageProtection\StorageKey;

#[PublicApi]
interface AuditOpaqueIdKeyProvider
{
    public function key(): StorageKey;
}
