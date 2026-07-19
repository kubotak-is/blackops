<?php

declare(strict_types=1);

namespace App\Security;

use BlackOps\Status\OperationStatusAuthorizationDecision;
use BlackOps\Status\OperationStatusAuthorizationRequest;
use BlackOps\Status\OperationStatusAuthorizer;

final readonly class SampleOperationStatusAuthorizer implements OperationStatusAuthorizer
{
    public function decide(OperationStatusAuthorizationRequest $request): OperationStatusAuthorizationDecision
    {
        $current = $request->currentActor();
        $origin = $request->originActor();

        if ($current === null || $origin === null) {
            return OperationStatusAuthorizationDecision::deny();
        }

        if ($current->type() !== 'user' || $origin->type() !== 'user') {
            return OperationStatusAuthorizationDecision::deny();
        }

        if ($current->id() !== $origin->id() || $current->type() !== $origin->type()) {
            return OperationStatusAuthorizationDecision::deny();
        }

        return OperationStatusAuthorizationDecision::allow();
    }
}
