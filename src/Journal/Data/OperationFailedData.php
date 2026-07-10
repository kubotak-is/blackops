<?php

declare(strict_types=1);

namespace BlackOps\Journal\Data;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Journal\JournalData;

#[PublicApi]
final readonly class OperationFailedData implements JournalData
{
    public function __construct(
        public string $errorType,
        public string $errorMessage,
        public bool $retryable,
    ) {}
}
