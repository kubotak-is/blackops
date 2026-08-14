<?php

declare(strict_types=1);

namespace App\Feature\Scheduled\DeferredProbe;

use BlackOps\Core\Outcome;

final readonly class ScheduledDeferredProbeOutcome implements Outcome
{
    public function __construct(
        public string $strategy,
    ) {}
}
