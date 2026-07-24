<?php

declare(strict_types=1);

namespace BlackOps\Outbox;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\OutboxRecordId;
use DateTimeImmutable;
use DateTimeZone;

#[PublicApi]
final readonly class OutboxRegistration
{
    public function __construct(
        private OutboxRecordId $recordId,
        private OperationId $operationId,
        DateTimeImmutable $recordedAt,
    ) {
        $this->recordedAt = $recordedAt->setTimezone(new DateTimeZone('UTC'));
    }

    private DateTimeImmutable $recordedAt;

    public function recordId(): OutboxRecordId
    {
        return $this->recordId;
    }

    public function operationId(): OperationId
    {
        return $this->operationId;
    }

    public function recordedAt(): DateTimeImmutable
    {
        return $this->recordedAt;
    }
}
