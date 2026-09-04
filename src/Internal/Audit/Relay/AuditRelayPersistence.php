<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Relay;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\Outbox\AuditOutboxLookupResult;
use BlackOps\Internal\Audit\Outbox\AuditOutboxRecord;
use BlackOps\Internal\Audit\Record\CanonicalAuditLogicalRecord;
use BlackOps\Internal\Audit\Storage\AuditAppendResult;

interface AuditRelayPersistence
{
    public function recoverNextExpired(): AuditOutboxLookupResult;

    public function claimNext(): AuditOutboxLookupResult;

    public function appendCanonical(#[\SensitiveParameter] CanonicalAuditLogicalRecord $logical): AuditAppendResult;

    public function retry(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
        #[\SensitiveParameter]
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
    ): AuditOutboxRecord;

    public function relay(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
        #[\SensitiveParameter]
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
    ): AuditOutboxRecord;

    public function poison(
        #[\SensitiveParameter]
        string $recordId,
        #[\SensitiveParameter]
        ?AuditTypedReference $expectedTenant,
        #[\SensitiveParameter]
        string $expectedPartitionId,
        #[\SensitiveParameter]
        int $expectedVersion,
        #[\SensitiveParameter]
        string $expectedToken,
    ): AuditOutboxRecord;
}
