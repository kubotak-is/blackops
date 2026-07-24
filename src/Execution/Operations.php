<?php

declare(strict_types=1);

namespace BlackOps\Execution;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\OperationValue;
use DateTimeImmutable;

#[PublicApi]
interface Operations
{
    /**
     * Register a deferred child operation in the active framework transaction.
     *
     * @param class-string<\BlackOps\Core\Operation> $definition
     */
    public function dispatch(
        string $definition,
        OperationValue $value,
        ?DateTimeImmutable $availableAt = null,
        ?ActorRef $executionActor = null,
    ): DispatchReceipt;
}
