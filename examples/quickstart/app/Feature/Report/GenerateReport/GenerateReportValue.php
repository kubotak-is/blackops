<?php

declare(strict_types=1);

namespace App\Feature\Report\GenerateReport;

use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\Attribute\SensitiveMode;
use BlackOps\Core\OperationValue;

final readonly class GenerateReportValue implements OperationValue
{
    public function __construct(
        public string $reportName,
        #[Sensitive(SensitiveMode::Mask)]
        public string $apiToken,
    ) {}
}
