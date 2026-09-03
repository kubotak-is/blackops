<?php

declare(strict_types=1);

namespace BlackOps\Audit;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\StorageProtection\StorageKey;

#[PublicApi]
interface AuditProtectionKeyProvider
{
    public function activeRecordKey(#[\SensitiveParameter] string $partitionId): StorageKey;

    public function recordKey(
        #[\SensitiveParameter]
        string $keyId,
        #[\SensitiveParameter]
        string $partitionId,
    ): StorageKey;

    public function activeOutboxKey(#[\SensitiveParameter] string $partitionId): StorageKey;

    public function outboxKey(
        #[\SensitiveParameter]
        string $keyId,
        #[\SensitiveParameter]
        string $partitionId,
    ): StorageKey;
}
