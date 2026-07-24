<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Identifier\OutboxRecordId;
use DateTimeImmutable;
use SensitiveParameter;

/**
 * A PostgreSQL outbox row leased by one relay instance.
 *
 * The claim is transport-owned because its lifecycle is enforced by the
 * PostgreSQL fencing and lease columns represented by PostgreSqlOutboxStore.
 */
final readonly class PostgreSqlOutboxClaim
{
    public function __construct(
        public OutboxRecordId $recordId,
        public DeferredOperationMessage $message,
        public string $relayId,
        #[SensitiveParameter]
        public int $fencingToken,
        public int $attemptCount,
        public DateTimeImmutable $leaseExpiresAt,
    ) {}
}
