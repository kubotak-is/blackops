<?php

declare(strict_types=1);

namespace BlackOps\Outbox;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use DateTimeImmutable;

#[PublicApi]
interface TransactionalOutbox
{
    public function register(
        Operation $definition,
        OperationValue $value,
        ?DateTimeImmutable $availableAt = null,
        ?ActorRef $executionActor = null,
    ): OutboxRegistration;
}
