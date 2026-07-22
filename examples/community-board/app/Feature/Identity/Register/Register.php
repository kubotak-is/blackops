<?php

declare(strict_types=1);

namespace App\Feature\Identity\Register;

use App\Domain\Identity\Exception\DuplicateEmail;
use App\Domain\Identity\Exception\RegistrationDisabled;
use App\Domain\Identity\IdentityService;
use BlackOps\Auth\Session\IssuedSession;
use BlackOps\Auth\Session\SessionManager;
use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Exception\OperationRejectedException;
use BlackOps\Core\Operation;
use BlackOps\Database\Attribute\Transactional;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'POST', path: '/auth/register')]
#[ExecuteWith('BlackOps\\Core\\Execution\\Inline')]
#[OperationType('auth.register')]
readonly class Register implements Operation
{
    public function __construct(
        private IdentityService $identity,
        private SessionManager $sessions,
    ) {}

    #[Transactional]
    public function handle(RegisterValue $value): RegistrationCompleted
    {
        try {
            $user = $this->identity->register($value->email, $value->displayName, $value->password);
        } catch (DuplicateEmail) {
            throw OperationRejectedException::conflict('auth.email_unavailable');
        } catch (RegistrationDisabled) {
            throw OperationRejectedException::forbidden('auth.registration_disabled');
        }

        return $this->outcome($this->sessions->issue($user->id));
    }

    private function outcome(IssuedSession $session): RegistrationCompleted
    {
        return new RegistrationCompleted(
            $session->token()->reveal(),
            $session->issuedAt()->format(DATE_ATOM),
            $session->expiresAt()->format(DATE_ATOM),
        );
    }
}
