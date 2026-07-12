<?php

declare(strict_types=1);

namespace App;

use App\Feature\Report\GenerateReport\GenerateReportHandler;
use App\Feature\Welcome\ShowWelcome\ShowWelcomeHandler;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;

final readonly class ApplicationServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(ShowWelcomeHandler::class);
        $services->autowire(GenerateReportHandler::class);
    }
}
