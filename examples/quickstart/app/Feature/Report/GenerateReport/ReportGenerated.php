<?php

declare(strict_types=1);

namespace App\Feature\Report\GenerateReport;

use BlackOps\Core\Outcome;

final readonly class ReportGenerated implements Outcome
{
    public function __construct(
        public string $reportName,
        public string $location,
    ) {}
}
