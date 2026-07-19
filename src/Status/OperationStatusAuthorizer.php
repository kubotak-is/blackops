<?php

declare(strict_types=1);

namespace BlackOps\Status;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
interface OperationStatusAuthorizer
{
    public function decide(OperationStatusAuthorizationRequest $request): OperationStatusAuthorizationDecision;
}
