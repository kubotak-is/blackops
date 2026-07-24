<?php

declare(strict_types=1);

namespace BlackOpsFrontendFixture\Feature\Report\GenerateReport;

use BlackOps\Core\Attribute\Deferred;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Operation;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'POST', path: '/reports')]
#[OperationType('report.generate')]
#[Deferred]
final readonly class GenerateReport implements Operation
{
    public function handle(GenerateReportValue $value): ReportGenerated
    {
        return new ReportGenerated($value->reportName, true);
    }
}
