<?php

declare(strict_types=1);

namespace BlackOps\Execution;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Validation\Violation;

interface ValidationRejectionRecorder
{
    /**
     * @return list<Violation>
     */
    public function validate(OperationValue $value): array;

    /**
     * @param list<Violation> $violations
     */
    public function rejectBinding(Operation $definition, array $violations): OperationId;

    /**
     * @param list<Violation> $violations
     */
    public function rejectValue(Operation $definition, OperationValue $value, array $violations): OperationId;
}
