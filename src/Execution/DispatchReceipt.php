<?php

declare(strict_types=1);

namespace BlackOps\Execution;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\OperationId;
use DateTimeImmutable;
use DateTimeZone;

#[PublicApi]
final readonly class DispatchReceipt
{
    public function __construct(
        private OperationId $operationId,
        DateTimeImmutable $dispatchedAt,
    ) {
        $this->dispatchedAt = $dispatchedAt->setTimezone(new DateTimeZone('UTC'));
    }

    private DateTimeImmutable $dispatchedAt;

    public function operationId(): OperationId
    {
        return $this->operationId;
    }

    public function dispatchedAt(): DateTimeImmutable
    {
        return $this->dispatchedAt;
    }
}
