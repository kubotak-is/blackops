<?php

declare(strict_types=1);

namespace BlackOps\Internal\StorageProtection;

use BlackOps\Core\TenantRef;
use BlackOps\StorageProtection\StoragePurpose;
use InvalidArgumentException;

final readonly class StorageProtectionContext
{
    public string $schemaVersion;

    public function __construct(
        public StoragePurpose $purpose,
        public string $recordIdentity,
        public string $operationId,
        public string $operationType,
        int $schemaVersion,
        #[\SensitiveParameter]
        public ?TenantRef $tenant = null,
    ) {
        foreach ([
            'record identity' => $recordIdentity,
            'operation id' => $operationId,
            'operation type' => $operationType,
        ] as $name => $value) {
            if ($value === '') {
                throw new InvalidArgumentException(sprintf('Storage protection %s must not be empty.', $name));
            }
        }

        if ($schemaVersion < 1) {
            throw new InvalidArgumentException('Storage protection schema version must be positive.');
        }
        $this->schemaVersion = (string) $schemaVersion;
    }
}
