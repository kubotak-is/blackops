<?php

declare(strict_types=1);

namespace App\Security;

use BlackOps\Core\Authorization\AuthorizationDecision;
use BlackOps\Core\Authorization\AuthorizationPolicy;
use BlackOps\Core\Authorization\AuthorizationRequest;

final readonly class SampleUserAuthorizationPolicy implements AuthorizationPolicy
{
    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        if ($request->actor()->type() !== 'user') {
            return AuthorizationDecision::forbid('authorization.sample_user_required');
        }

        return AuthorizationDecision::allow();
    }
}
