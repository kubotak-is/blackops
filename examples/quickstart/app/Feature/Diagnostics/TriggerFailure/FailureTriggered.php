<?php

declare(strict_types=1);

namespace App\Feature\Diagnostics\TriggerFailure;

use BlackOps\Core\Outcome;

final readonly class FailureTriggered implements Outcome
{
    public function __construct(
        public string $reference,
    ) {}
}
