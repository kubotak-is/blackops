<?php

declare(strict_types=1);

namespace App;

use App\Feature\Report\GenerateReport\GenerateReport;
use App\Feature\Welcome\ShowWelcome\ShowWelcome;
use BlackOps\Core\Registry\OperationProvider;

final readonly class ApplicationOperationProvider implements OperationProvider
{
    public function definitions(): iterable
    {
        return [ShowWelcome::class, GenerateReport::class];
    }
}
