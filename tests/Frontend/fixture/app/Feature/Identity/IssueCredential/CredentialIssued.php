<?php

declare(strict_types=1);

namespace BlackOpsFrontendFixture\Feature\Identity\IssueCredential;

use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\EphemeralOutcome;

final readonly class CredentialIssued implements EphemeralOutcome
{
    public function __construct(
        #[Sensitive]
        public string $token,
        public string $expiresAt,
    ) {}
}
