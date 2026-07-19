<?php

declare(strict_types=1);

namespace BlackOps\Status;

use BlackOps\Core\Attribute\PublicApi;

#[PublicApi]
final readonly class OperationStatusFound implements OperationStatusResult
{
    public function __construct(
        private OperationStatus $status,
    ) {}

    public function status(): OperationStatus
    {
        return $this->status;
    }
}
