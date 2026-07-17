<?php

declare(strict_types=1);

namespace BlackOps\Http\Authentication;

use BlackOps\Core\Attribute\PublicApi;
use Psr\Http\Message\ServerRequestInterface;

#[PublicApi]
interface HttpAuthenticator
{
    public function authenticate(ServerRequestInterface $request): AuthenticationResult;
}
