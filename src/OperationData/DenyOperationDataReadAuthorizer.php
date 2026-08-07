<?php

declare(strict_types=1);

namespace BlackOps\OperationData;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
final readonly class DenyOperationDataReadAuthorizer implements OperationDataReadAuthorizer
{
    public function decide(OperationDataReadAuthorizationRequest $request): OperationDataReadAuthorizationDecision
    {
        return OperationDataReadAuthorizationDecision::deny();
    }
}
