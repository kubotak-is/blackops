<?php

declare(strict_types=1);

namespace BlackOps\Journal;

use BlackOps\Core\Identifier\OperationId;

interface CanonicalJournalReader
{
    /** @return iterable<JournalRecord> */
    public function records(OperationId $operationId): iterable;
}
