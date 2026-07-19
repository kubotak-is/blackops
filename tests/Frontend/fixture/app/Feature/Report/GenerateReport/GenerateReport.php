<?php

declare(strict_types=1);

namespace BlackOpsFrontendFixture\Feature\Report\GenerateReport;

use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Operation;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'POST', path: '/reports')]
#[OperationType('report.generate')]
#[ExecuteWith(Deferred::class)]
final readonly class GenerateReport implements Operation
{
    public function handle(GenerateReportValue $value): ReportGenerated
    {
        return new ReportGenerated($value->reportName, true);
    }
}
