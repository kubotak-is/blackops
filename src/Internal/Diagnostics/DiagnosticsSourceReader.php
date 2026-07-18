<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

use BlackOps\Core\Identifier\OperationId;

interface DiagnosticsSourceReader
{
    public function deferredState(OperationId $operationId): ?DiagnosticsDeferredState;

    public function deadLetter(OperationId $operationId): ?DiagnosticsDeadLetter;

    /** @return list<DiagnosticsPurgeAudit> */
    public function purgeAudits(OperationId $operationId): array;
}
