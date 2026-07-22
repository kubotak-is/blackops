<?php

declare(strict_types=1);

namespace App\Feature\Identity\Login;

use App\Domain\Identity\Exception\InvalidCredentials;
use App\Domain\Identity\IdentityService;
use BlackOps\Auth\Session\IssuedSession;
use BlackOps\Auth\Session\SessionManager;
use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Exception\OperationRejectedException;
use BlackOps\Core\Operation;
use BlackOps\Database\Attribute\Transactional;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'POST', path: '/auth/login')]
#[ExecuteWith('BlackOps\\Core\\Execution\\Inline')]
#[OperationType('auth.login')]
readonly class Login implements Operation
{
    public function __construct(
        private IdentityService $identity,
        private SessionManager $sessions,
    ) {}

    #[Transactional]
    public function handle(LoginValue $value): LoginCompleted
    {
        try {
            $user = $this->identity->authenticate($value->email, $value->password);
        } catch (InvalidCredentials) {
            throw OperationRejectedException::unauthorized('auth.invalid_credentials');
        }

        $current = $value->currentToken === null ? null : $this->sessions->authenticate($value->currentToken);
        if ($current !== null && $current->type() === 'user' && $current->id() === $user->id) {
            $session = $this->sessions->rotate($value->currentToken);
        } else {
            if ($current !== null) {
                $this->sessions->revoke($value->currentToken);
            }

            $session = $this->sessions->issue($user->id);
        }

        return $this->outcome($session);
    }

    private function outcome(IssuedSession $session): LoginCompleted
    {
        return new LoginCompleted(
            $session->token()->reveal(),
            $session->issuedAt()->format(DATE_ATOM),
            $session->expiresAt()->format(DATE_ATOM),
        );
    }
}
