<?php

declare(strict_types=1);

namespace BlackOps\Journal\Data;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\OperationValue;
use BlackOps\Journal\JournalData;

#[PublicApi]
final readonly class OperationReceivedData implements JournalData
{
    public function __construct(
        public OperationValue $value,
    ) {}
}
