<?php

declare(strict_types=1);

namespace BlackOps\Outcome;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Outcome;
use DateTimeImmutable;
use DateTimeZone;

#[PublicApi]
final readonly class OutcomeRecord
{
    private DateTimeImmutable $completedAt;

    public function __construct(
        private OperationId $operationId,
        private Outcome $outcome,
        DateTimeImmutable $completedAt,
    ) {
        $this->completedAt = $completedAt->setTimezone(new DateTimeZone('UTC'));
    }

    public function operationId(): OperationId
    {
        return $this->operationId;
    }

    public function outcome(): Outcome
    {
        return $this->outcome;
    }

    public function completedAt(): DateTimeImmutable
    {
        return $this->completedAt;
    }
}
