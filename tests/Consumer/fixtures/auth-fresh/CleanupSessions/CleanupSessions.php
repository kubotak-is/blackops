<?php

declare(strict_types=1);

namespace App\Feature\AuthProbe\CleanupSessions;

use BlackOps\Auth\Session\SessionManager;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Operation;
use BlackOps\Database\Attribute\Transactional;
use BlackOps\Http\Attribute\Route;
use DateTimeImmutable;

#[Route(method: 'POST', path: '/auth-probe/cleanup')]
#[OperationType('auth.probe.cleanup')]
readonly class CleanupSessions implements Operation
{
    public function __construct(
        private SessionManager $sessions,
    ) {}

    #[Transactional]
    public function handle(CleanupSessionsValue $value): SessionsCleaned
    {
        return new SessionsCleaned($this->sessions->cleanup(new DateTimeImmutable($value->retentionCutoff)));
    }
}
