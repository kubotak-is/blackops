<?php

declare(strict_types=1);

namespace App\Feature\Identity\ShowCurrentUser;

use BlackOps\Core\Outcome;

final readonly class CurrentUserShown implements Outcome
{
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
    ) {}
}
