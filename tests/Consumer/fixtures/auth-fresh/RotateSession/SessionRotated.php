<?php

declare(strict_types=1);

namespace App\Feature\AuthProbe\RotateSession;

use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\EphemeralOutcome;

final readonly class SessionRotated implements EphemeralOutcome
{
    public function __construct(
        #[Sensitive]
        public string $token,
        public string $issuedAt,
        public string $expiresAt,
    ) {}
}
