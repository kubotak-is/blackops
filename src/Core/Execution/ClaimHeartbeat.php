<?php

declare(strict_types=1);

namespace BlackOps\Core\Execution;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
interface ClaimHeartbeat
{
    public function heartbeat(OperationClaim $claim): OperationClaim;
}
