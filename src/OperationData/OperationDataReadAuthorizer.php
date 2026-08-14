<?php

declare(strict_types=1);

namespace BlackOps\OperationData;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
interface OperationDataReadAuthorizer
{
    public function decide(OperationDataReadAuthorizationRequest $request): OperationDataReadAuthorizationDecision;
}
