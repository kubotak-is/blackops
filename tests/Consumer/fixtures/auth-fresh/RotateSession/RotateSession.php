<?php

declare(strict_types=1);

namespace App\Feature\AuthProbe\RotateSession;

use BlackOps\Auth\Session\InvalidSessionException;
use BlackOps\Auth\Session\SessionManager;
use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Exception\OperationRejectedException;
use BlackOps\Core\Operation;
use BlackOps\Database\Attribute\Transactional;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'POST', path: '/auth-probe/rotate')]
#[ExecuteWith('BlackOps\\Core\\Execution\\Inline')]
#[OperationType('auth.probe.rotate')]
readonly class RotateSession implements Operation
{
    public function __construct(
        private SessionManager $sessions,
    ) {}

    #[Transactional]
    public function handle(RotateSessionValue $value): SessionRotated
    {
        try {
            $session = $this->sessions->rotate($value->token);
        } catch (InvalidSessionException) {
            throw OperationRejectedException::unauthorized('auth.invalid_session');
        }

        return new SessionRotated(
            $session->token()->reveal(),
            $session->issuedAt()->format(DATE_ATOM),
            $session->expiresAt()->format(DATE_ATOM),
        );
    }
}
