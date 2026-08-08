<?php

declare(strict_types=1);

namespace BlackOps\Http;

use BlackOps\Core\ActorContext;
use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\TenantRef;
use BlackOps\Idempotency\IdempotencyKey;
use BlackOps\Telemetry\TelemetryContext;

interface DeferredOperationAcceptor
{
    public function accepts(Operation $definition): bool;

    /** @mago-expect lint:excessive-parameter-list */
    public function accept(
        Operation $definition,
        OperationValue $value,
        ?ActorContext $actorContext = null,
        ?IdempotencyKey $idempotencyKey = null,
        ?TenantRef $tenant = null,
        ?TelemetryContext $telemetry = null,
    ): DeferredAcknowledgement|OperationResult;
}
