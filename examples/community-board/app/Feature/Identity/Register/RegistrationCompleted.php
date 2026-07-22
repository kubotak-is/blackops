<?php

declare(strict_types=1);

namespace App\Feature\Identity\Register;

use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\EphemeralOutcome;

final readonly class RegistrationCompleted implements EphemeralOutcome
{
    public function __construct(
        #[Sensitive]
        public string $token,
        public string $issuedAt,
        public string $expiresAt,
    ) {}
}
