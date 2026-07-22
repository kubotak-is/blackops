<?php

declare(strict_types=1);

namespace BlackOps\Auth\Session;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Http\Authentication\AuthenticationResult;
use BlackOps\Http\Authentication\HttpAuthenticator;
use Psr\Http\Message\ServerRequestInterface;
use SensitiveParameter;

#[PublicApi]
final readonly class BearerSessionAuthenticator implements HttpAuthenticator
{
    public function __construct(
        private SessionManager $sessions,
    ) {}

    public function authenticate(ServerRequestInterface $request): AuthenticationResult
    {
        $values = $request->getHeader('Authorization');

        if ($values === []) {
            return AuthenticationResult::anonymous();
        }

        $matches = [];

        if (count($values) !== 1 || preg_match('/^Bearer ([^\s]+)$/D', $values[0], $matches) !== 1) {
            return AuthenticationResult::invalid('authentication.invalid_session');
        }

        return $this->authenticateToken($matches[1]);
    }

    private function authenticateToken(#[SensitiveParameter] string $token): AuthenticationResult
    {
        $actor = $this->sessions->authenticate($token);

        return $actor === null
            ? AuthenticationResult::invalid('authentication.invalid_session')
            : AuthenticationResult::authenticated($actor);
    }
}
