<?php

declare(strict_types=1);

namespace App\Security;

use BlackOps\Status\OperationStatusAuthorizationDecision;
use BlackOps\Status\OperationStatusAuthorizationRequest;
use BlackOps\Status\OperationStatusAuthorizer;

final readonly class BoardOperationStatusAuthorizer implements OperationStatusAuthorizer
{
    public function decide(OperationStatusAuthorizationRequest $request): OperationStatusAuthorizationDecision
    {
        if ($request->operationType() !== 'board.digest.weekly.generate') {
            return OperationStatusAuthorizationDecision::deny();
        }

        $current = $request->currentActor();
        $origin = $request->originActor();
        if ($current === null || $origin === null || $current->type() !== 'user' || $origin->type() !== 'user') {
            return OperationStatusAuthorizationDecision::deny();
        }

        return $current->id() === $origin->id()
            ? OperationStatusAuthorizationDecision::allow()
            : OperationStatusAuthorizationDecision::deny();
    }
}
