<?php

declare(strict_types=1);

namespace BlackOps\Auth\Session;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
interface SessionIdentityProvider
{
    public function resolve(string $identityId): ?ActorRef;
}
