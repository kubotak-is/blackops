<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

use BlackOps\Core\Audit\AuditReadAuthorizationDecision;

final readonly class UnavailableOperationDiagnosticsAuthorizer implements OperationDiagnosticsAuthorizer
{
    public function decide(
        #[\SensitiveParameter]
        OperationDiagnosticsAuthorizationRequest $request,
    ): AuditReadAuthorizationDecision {
        return AuditReadAuthorizationDecision::ResourceUnavailable;
    }
}
