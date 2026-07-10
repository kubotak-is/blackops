<?php

declare(strict_types=1);

namespace BlackOps\Core\Retention;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\RetentionPurgeAuditId;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

#[PublicApi]
final readonly class RetentionPurgeAuditRecord
{
    public function __construct(
        private RetentionPurgeAuditId $id,
        private OperationId $operationId,
        private RetentionPurgeTarget $target,
        private int $affectedCount,
        private RetentionPolicyRef $policy,
        private DateTimeImmutable $purgedAt,
        private RetentionActorRef $purgedBy,
    ) {
        if ($affectedCount < 1) {
            throw new InvalidArgumentException('Retention purge affected count must be positive.');
        }
    }

    public function id(): RetentionPurgeAuditId
    {
        return $this->id;
    }

    public function operationId(): OperationId
    {
        return $this->operationId;
    }

    public function target(): RetentionPurgeTarget
    {
        return $this->target;
    }

    public function affectedCount(): int
    {
        return $this->affectedCount;
    }

    public function policy(): RetentionPolicyRef
    {
        return $this->policy;
    }

    public function purgedAt(): DateTimeImmutable
    {
        return $this->purgedAt->setTimezone(new DateTimeZone('UTC'));
    }

    public function purgedBy(): RetentionActorRef
    {
        return $this->purgedBy;
    }
}
