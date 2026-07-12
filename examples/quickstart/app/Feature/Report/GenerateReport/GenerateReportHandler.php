<?php

declare(strict_types=1);

namespace App\Feature\Report\GenerateReport;

use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use LogicException;

/** @implements OperationHandler<GenerateReportValue, ReportGenerated> */
final readonly class GenerateReportHandler implements OperationHandler
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
