<?php

declare(strict_types=1);

namespace App\Feature\Welcome\ShowWelcome;

use BlackOps\Core\Outcome;

final readonly class WelcomeShown implements Outcome
{
    public function __construct(
        public string $message,
    ) {}
}
