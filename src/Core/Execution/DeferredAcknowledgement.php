<?php

declare(strict_types=1);

namespace BlackOps\Core\Execution;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\OperationId;
use DateTimeImmutable;

#[PublicApi]
final readonly class DeferredAcknowledgement
{
    public function __construct(
        private OperationId $operationId,
        private DateTimeImmutable $acceptedAt,
        private bool $replayed = false,
    ) {}

    public function operationId(): OperationId
    {
        return $this->operationId;
    }

    public function acceptedAt(): DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function isReplayed(): bool
    {
        return $this->replayed;
    }
}
