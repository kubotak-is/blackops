<?php

declare(strict_types=1);

namespace BlackOps\Execution;

use BlackOps\Core\ActorContext;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\TenantRef;
use BlackOps\Idempotency\IdempotencyKey;
use BlackOps\Telemetry\TelemetryContext;

#[PublicApi]
interface Dispatcher
{
    /** @mago-expect lint:excessive-parameter-list */
    public function dispatch(
        Operation $definition,
        OperationValue $value,
        ?ActorContext $actorContext = null,
        ?IdempotencyKey $idempotencyKey = null,
        ?TenantRef $tenant = null,
        ?TelemetryContext $telemetry = null,
    ): OperationResult;
}
