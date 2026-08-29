<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Protection;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\StorageProtection\StorageKey;

interface AuditProtectionKeySource
{
    public function activeKey(?AuditTypedReference $tenant, AuditProtectionPurpose $purpose): StorageKey;

    public function key(
        #[\SensitiveParameter]
        string $keyId,
        ?AuditTypedReference $tenant,
        AuditProtectionPurpose $purpose,
    ): StorageKey;
}
