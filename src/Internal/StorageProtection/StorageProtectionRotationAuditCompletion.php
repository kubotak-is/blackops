<?php

declare(strict_types=1);

namespace BlackOps\Internal\StorageProtection;

final readonly class StorageProtectionRotationAuditCompletion
{
    public function __construct(
        public StorageProtectionRotationScope $scope,
        public StorageProtectionRotationCounts $counts,
        public string $state,
        public ?string $auditId,
        public ?string $failureFingerprint,
    ) {}
}
