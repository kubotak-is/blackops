<?php

declare(strict_types=1);

namespace App\Feature\Scheduled\InlineProbe;

use BlackOps\Core\Outcome;

final readonly class ScheduledInlineProbeOutcome implements Outcome
{
    public function __construct(
        public string $strategy,
    ) {}
}
