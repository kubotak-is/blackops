<?php

declare(strict_types=1);

namespace BlackOps\OperationData;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Outcome\OutcomeRecord;

#[PublicApi]
final readonly class OperationOutcomeFound implements OperationOutcomeReadResult
{
    public function __construct(
        private OutcomeRecord $record,
    ) {}

    public function record(): OutcomeRecord
    {
        return $this->record;
    }
}
