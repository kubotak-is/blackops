<?php

declare(strict_types=1);

namespace App\Feature\Report\GenerateReport;

use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\Attribute\SensitiveMode;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Validation\Attribute\NotBlank;
use SensitiveParameter;

final readonly class GenerateReportValue implements OperationValue
{
    public function __construct(
        #[NotBlank]
        public string $reportName,
        #[Sensitive(SensitiveMode::Mask)]
        #[SensitiveParameter]
        #[NotBlank]
        public string $recipientEmail,
    ) {}
}
