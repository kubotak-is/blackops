<?php

declare(strict_types=1);

namespace BlackOps\Internal\OperationData;

use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Journal\JournalRecord;

interface TenantScopedCanonicalJournalReader
{
    /** @return iterable<JournalRecord> */
    public function recordsForTenant(OperationId $operationId, ?TenantRef $tenant): iterable;
}
