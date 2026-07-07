<?php

declare(strict_types=1);

namespace BlackOps\Journal\Data;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Outcome;
use BlackOps\Journal\JournalData;

#[PublicApi]
final readonly class OperationCompletedData implements JournalData
{
    public function __construct(
        public Outcome $outcome,
    ) {}
}
