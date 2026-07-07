<?php

declare(strict_types=1);

namespace BlackOps\Journal\Data;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Journal\JournalData;

#[PublicApi]
final readonly class OperationRejectedData implements JournalData
{
    public function __construct(
        public RejectionReason $reason,
    ) {}
}
