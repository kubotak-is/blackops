<?php

declare(strict_types=1);

namespace BlackOps\Internal\Audit\Relay;

use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Internal\Audit\Outbox\AuditOutboxLookupResult;
use BlackOps\Internal\Audit\Outbox\AuditOutboxRecord;
use BlackOps\Internal\Audit\Outbox\PostgreSqlAuditOutboxStore;
use BlackOps\Internal\Audit\Record\CanonicalAuditLogicalRecord;
use BlackOps\Internal\Audit\Storage\AuditAppendResult;
use BlackOps\Internal\Audit\Storage\PostgreSqlAuditRecordStore;

final readonly class PostgreSqlAuditRelayPersistence implements AuditRelayPersistence
{
    public function __construct(
        private PostgreSqlAuditOutboxStore $outbox,
        private PostgreSqlAuditRecordStore $records,
    ) {}

    public function recoverNextExpired(): AuditOutboxLookupResult
    {
        return $this->outbox->recoverNextExpired();
    }

    public function claimNext(): AuditOutboxLookupResult
    {
        return $this->outbox->claimNext();
    }

    public function appendCanonical(#[\SensitiveParameter] CanonicalAuditLogicalRecord $logical): AuditAppendResult
    {
        return $this->records->appendDurable($logical);
    }

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
    ): AuditOutboxRecord {
        return $this->outbox->retry($recordId, $expectedTenant, $expectedPartitionId, $expectedVersion, $expectedToken);
    }

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
    ): AuditOutboxRecord {
        return $this->outbox->relay($recordId, $expectedTenant, $expectedPartitionId, $expectedVersion, $expectedToken);
    }

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
    ): AuditOutboxRecord {
        return $this->outbox->poison(
            $recordId,
            $expectedTenant,
            $expectedPartitionId,
            $expectedVersion,
            $expectedToken,
        );
    }
}
