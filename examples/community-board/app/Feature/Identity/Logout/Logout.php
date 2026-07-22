<?php

declare(strict_types=1);

namespace App\Feature\Identity\Logout;

use BlackOps\Auth\Session\SessionManager;
use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Operation;
use BlackOps\Database\Attribute\Transactional;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'POST', path: '/auth/logout')]
#[ExecuteWith('BlackOps\\Core\\Execution\\Inline')]
#[OperationType('auth.logout')]
readonly class Logout implements Operation
{
    public function __construct(
        private SessionManager $sessions,
    ) {}

    #[Transactional]
    public function handle(LogoutValue $value): LogoutCompleted
    {
        $this->sessions->revoke($value->token);

        return new LogoutCompleted();
    }
}
