<?php

declare(strict_types=1);

namespace BlackOps\Journal;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\OperationId;

#[PublicApi]
interface CanonicalJournalReader
{
    /** @return iterable<JournalRecord> */
    public function records(OperationId $operationId): iterable;
}
