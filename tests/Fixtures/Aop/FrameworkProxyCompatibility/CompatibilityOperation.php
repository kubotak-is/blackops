<?php

declare(strict_types=1);

namespace BlackOps\Tests\Fixtures\Aop\FrameworkProxyCompatibility;

use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Operation;
use BlackOps\Database\Attribute\Transactional;

#[OperationType('compatibility.operation')]
#[Transactional]
class CompatibilityOperation implements Operation
{
    public int $calls = 0;

    public function handle(CompatibilityOperationValue $value): CompatibilityOperationOutcome
    {
        $this->calls++;

        return new CompatibilityOperationOutcome();
    }
}
