<?php

declare(strict_types=1);

namespace BlackOps\Core\Retention;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\RetentionHoldId;
use DateTimeImmutable;

#[PublicApi]
interface RetentionHoldPort
{
    public function place(
        OperationId $operationId,
        RetentionHoldCategory $category,
        string $reason,
        RetentionActorRef $placedBy,
        DateTimeImmutable $placedAt,
    ): RetentionHold;

    public function release(
        RetentionHoldId $holdId,
        RetentionActorRef $releasedBy,
        DateTimeImmutable $releasedAt,
    ): RetentionHold;

    /**
     * @return list<RetentionHold>
     */
    public function activeFor(OperationId $operationId): array;
}
