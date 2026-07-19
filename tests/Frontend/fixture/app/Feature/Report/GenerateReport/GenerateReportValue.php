<?php

declare(strict_types=1);

namespace BlackOpsFrontendFixture\Feature\Report\GenerateReport;

use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Validation\Attribute\Length;
use BlackOps\Core\Validation\Attribute\NotBlank;

final readonly class GenerateReportValue implements OperationValue
{
    public function __construct(
        #[NotBlank]
        #[Length(min: 3, max: 64)]
        public string $reportName,
        #[Sensitive]
        #[NotBlank]
        public string $recipientEmail,
    ) {}
}
