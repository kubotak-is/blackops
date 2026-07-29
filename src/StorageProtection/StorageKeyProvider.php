<?php

declare(strict_types=1);

namespace BlackOps\StorageProtection;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\TenantRef;

#[PublicApi]
interface StorageKeyProvider
{
    public function activeKey(?TenantRef $tenant, StoragePurpose $purpose): StorageKey;

    public function key(string $keyId, ?TenantRef $tenant, StoragePurpose $purpose): StorageKey;
}
