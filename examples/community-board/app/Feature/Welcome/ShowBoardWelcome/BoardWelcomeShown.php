<?php

declare(strict_types=1);

namespace App\Feature\Welcome\ShowBoardWelcome;

use BlackOps\Core\Outcome;

final readonly class BoardWelcomeShown implements Outcome
{
    public function __construct(
        public string $message,
        public string $summary,
    ) {}
}
