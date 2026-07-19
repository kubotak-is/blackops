<?php

declare(strict_types=1);

namespace App\Security;

use App\Identity\BearerToken;
use App\Identity\IdentityClock;
use App\Identity\IdentityRepository;
use App\Identity\SessionToken;
use BlackOps\Core\ActorRef;
use BlackOps\Http\Authentication\AuthenticationResult;
use BlackOps\Http\Authentication\HttpAuthenticator;
use Psr\Http\Message\ServerRequestInterface;

final readonly class SessionHttpAuthenticator implements HttpAuthenticator
{
    public function __construct(
        private IdentityRepository $repository,
        private SessionToken $tokens,
        private IdentityClock $clock,
    ) {}

    public function authenticate(ServerRequestInterface $request): AuthenticationResult
    {
        $bearer = BearerToken::fromRequest($request);
        if (!$bearer->present) {
            return AuthenticationResult::anonymous();
        }

        if (!$bearer->valid || $bearer->rawToken === null) {
            return AuthenticationResult::invalid('authentication.invalid_session');
        }

        $user = $this->repository->findByActiveTokenHash($this->tokens->hash($bearer->rawToken), $this->clock->now());
        if ($user === null) {
            return AuthenticationResult::invalid('authentication.invalid_session');
        }

        return AuthenticationResult::authenticated(new ActorRef($user->id, 'user'));
    }
}
