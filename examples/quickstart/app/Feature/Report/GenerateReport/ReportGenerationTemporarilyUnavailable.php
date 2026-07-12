<?php

declare(strict_types=1);

namespace App\Feature\Report\GenerateReport;

use BlackOps\Core\Supervision\RetryableException;
use RuntimeException;

final class ReportGenerationTemporarilyUnavailable extends RuntimeException implements RetryableException {}
