<?php

declare(strict_types=1);

namespace BlackOps\Internal\Status;

use BlackOps\Status\OperationStatus;

final readonly class OperationStatusDetail implements OperationStatusDetailResult
{
    public function __construct(
        public OperationStatus $status,
    ) {}
}
