<?php

declare(strict_types=1);

namespace App\Feature\AuthProbe\CleanupSessions;

use BlackOps\Core\OperationValue;
use BlackOps\Core\Validation\Attribute\NotBlank;

final readonly class CleanupSessionsValue implements OperationValue
{
    public function __construct(
        #[NotBlank]
        public string $retentionCutoff,
    ) {}
}
