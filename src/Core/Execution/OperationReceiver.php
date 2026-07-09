<?php

declare(strict_types=1);

namespace BlackOps\Core\Execution;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
interface OperationReceiver
{
    public function claim(ClaimRequest $request): ?OperationClaim;
}
