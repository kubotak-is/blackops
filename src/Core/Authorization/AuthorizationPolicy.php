<?php

declare(strict_types=1);

namespace BlackOps\Core\Authorization;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
interface AuthorizationPolicy
{
    public function decide(AuthorizationRequest $request): AuthorizationDecision;
}
