<?php

declare(strict_types=1);

namespace App\Feature\Report\GenerateReport;

use BlackOps\Core\Attribute\Accepts;
use BlackOps\Core\Attribute\ExecuteWith;
use BlackOps\Core\Attribute\HandledBy;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Attribute\Returns;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Operation;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'POST', path: '/reports')]
#[OperationType('report.generate')]
#[Accepts(GenerateReportValue::class)]
#[HandledBy(GenerateReportHandler::class)]
#[Returns(ReportGenerated::class)]
#[ExecuteWith(Deferred::class)]
final readonly class GenerateReport implements Operation {}
