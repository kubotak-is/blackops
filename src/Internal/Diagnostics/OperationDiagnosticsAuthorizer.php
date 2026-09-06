<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

use BlackOps\Core\Audit\AuditReadAuthorizationDecision;

interface OperationDiagnosticsAuthorizer
{
    public function decide(
        #[\SensitiveParameter]
        OperationDiagnosticsAuthorizationRequest $request,
    ): AuditReadAuthorizationDecision;
}
