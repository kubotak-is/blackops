<?php

declare(strict_types=1);

namespace App\Feature\AuthProbe\CleanupSessions;

use BlackOps\Core\Outcome;

final readonly class SessionsCleaned implements Outcome
{
    public function __construct(
        public int $deleted,
    ) {}
}
