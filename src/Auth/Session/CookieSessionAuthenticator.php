<?php

declare(strict_types=1);

namespace BlackOps\Auth\Session;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Http\Authentication\AuthenticationResult;
use BlackOps\Http\Authentication\HttpAuthenticator;
use Psr\Http\Message\ServerRequestInterface;

#[PublicApi]
final readonly class CookieSessionAuthenticator implements HttpAuthenticator
{
    public function __construct(
        private SessionManager $sessions,
        private SessionCookieName $cookie,
    ) {}

    public function authenticate(ServerRequestInterface $request): AuthenticationResult
    {
        $cookies = $request->getCookieParams();

        if (!array_key_exists($this->cookie->value(), $cookies)) {
            return AuthenticationResult::anonymous();
        }

        if (!is_string($cookies[$this->cookie->value()])) {
            return AuthenticationResult::invalid('authentication.invalid_session');
        }

        $token = (string) $cookies[$this->cookie->value()];

        $actor = $this->sessions->authenticate($token);

        return $actor === null
            ? AuthenticationResult::invalid('authentication.invalid_session')
            : AuthenticationResult::authenticated($actor);
    }
}
