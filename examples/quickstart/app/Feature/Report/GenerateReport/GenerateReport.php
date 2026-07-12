<?php

declare(strict_types=1);

namespace App\Feature\Report\GenerateReport;

use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationResult;
use BlackOps\Http\Attribute\Route;
use LogicException;

#[Route(method: 'POST', path: '/reports')]
#[OperationType('report.generate')]
#[Accepts(GenerateReportValue::class)]
#[Returns(ReportGenerated::class)]
#[ExecuteWith(Deferred::class)]
final readonly class GenerateReport implements Operation
{
    public function handle(GenerateReportValue $value, ExecutionContext $context): OperationResult
    {
        $attempt = $context->attempt();

        if ($attempt === null) {
            throw new LogicException('Report handler requires a deferred attempt.');
        }

        if ($attempt->number() === 1) {
            throw new ReportGenerationTemporarilyUnavailable('Report backend is temporarily unavailable.');
        }

        return OperationResult::completed(
            new ReportGenerated($value->reportName, '/reports/generated/' . $value->reportName . '.json'),
        );
    }
}
