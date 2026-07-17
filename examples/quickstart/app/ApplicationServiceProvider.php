<?php

declare(strict_types=1);

namespace App;

use App\UserInterface\Http\SampleTokenAuthenticator;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Http\Authentication\HttpAuthenticator;

final readonly class ApplicationServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(HttpAuthenticator::class, SampleTokenAuthenticator::class);
    }
}
