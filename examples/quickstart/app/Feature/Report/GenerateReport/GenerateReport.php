<?php

declare(strict_types=1);

namespace App\Feature\Report\GenerateReport;

use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Http\Attribute\Route;
use LogicException;

#[Route(method: 'POST', path: '/reports')]
#[OperationType('report.generate')]
#[Accepts(GenerateReportValue::class)]
#[Returns(ReportGenerated::class)]
#[ExecuteWith(Deferred::class)]
/** @implements OperationHandler<GenerateReportValue, ReportGenerated> */
final readonly class GenerateReport implements Operation, OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        $value = $operation->value();
        $attempt = $operation->context()->attempt();

        if (!$value instanceof GenerateReportValue || $attempt === null) {
            throw new LogicException('Report handler requires a deferred report attempt.');
        }

        if ($attempt->number() === 1) {
            throw new ReportGenerationTemporarilyUnavailable('Report backend is temporarily unavailable.');
        }

        return OperationResult::completed(
            new ReportGenerated($value->reportName, '/reports/generated/' . $value->reportName . '.json'),
        );
    }
}
