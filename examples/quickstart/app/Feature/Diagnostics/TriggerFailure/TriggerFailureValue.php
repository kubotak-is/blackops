<?php

declare(strict_types=1);

namespace App\Feature\Diagnostics\TriggerFailure;

use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\Attribute\SensitiveMode;
use BlackOps\Core\OperationValue;

final readonly class TriggerFailureValue implements OperationValue
{
    public function __construct(
        public string $reference,
        #[Sensitive(SensitiveMode::Mask)]
        public string $sensitiveNote,
    ) {}
}
