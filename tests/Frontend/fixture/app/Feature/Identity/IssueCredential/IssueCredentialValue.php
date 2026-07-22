<?php

declare(strict_types=1);

namespace BlackOpsFrontendFixture\Feature\Identity\IssueCredential;

use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\OperationValue;

final readonly class IssueCredentialValue implements OperationValue
{
    public function __construct(
        public string $identity,
        #[Sensitive]
        public string $password,
    ) {}
}
