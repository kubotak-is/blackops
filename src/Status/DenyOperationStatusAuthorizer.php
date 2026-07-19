<?php

declare(strict_types=1);

namespace BlackOps\Status;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
final readonly class DenyOperationStatusAuthorizer implements OperationStatusAuthorizer
{
    public function decide(OperationStatusAuthorizationRequest $request): OperationStatusAuthorizationDecision
    {
        return OperationStatusAuthorizationDecision::deny();
    }
}
