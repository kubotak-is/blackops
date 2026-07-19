<?php

declare(strict_types=1);

namespace BlackOps\Internal\Status;

use BlackOps\Status\OperationStatus;

final readonly class OperationStatusDetail
{
    public function __construct(
        public OperationStatus $status,
    ) {}
}
