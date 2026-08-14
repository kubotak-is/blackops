<?php

declare(strict_types=1);

namespace BlackOps\Internal\StorageProtection;

use BlackOps\Core\TenantRef;
use BlackOps\StorageProtection\StoragePurpose;

final readonly class StorageProtectionRotationScope
{
    public function __construct(
        public StoragePurpose $purpose,
        public ?TenantRef $tenant,
        public string $oldKeyId,
        public string $newKeyId,
        public int $batchSize,
        public string $checkpoint,
        public ?string $actor = null,
        public ?string $reason = null,
        public bool $confirmed = false,
    ) {
        new StorageProtectionRotationScopeValidator()->validate($this);
    }

    public function scopeHash(): string
    {
        return hash('sha256', implode("\0", [
            $this->purpose->value,
            $this->tenant?->type() ?? '',
            $this->tenant?->id() ?? '',
            $this->oldKeyId,
            $this->newKeyId,
            $this->checkpoint,
        ]));
    }
}
