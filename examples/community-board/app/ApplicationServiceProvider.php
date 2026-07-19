<?php

declare(strict_types=1);

namespace App;

use App\Identity\DoctrineIdentityRepository;
use App\Identity\IdentityClock;
use App\Identity\IdentityRepository;
use App\Identity\SessionToken;
use App\Identity\SystemIdentityClock;
use App\Security\SessionHttpAuthenticator;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Http\Authentication\HttpAuthenticator;

final readonly class ApplicationServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(IdentityRepository::class, DoctrineIdentityRepository::class);
        $services->autowire(IdentityClock::class, SystemIdentityClock::class);
        $services->autowire(SessionToken::class);
        $services->autowire(HttpAuthenticator::class, SessionHttpAuthenticator::class);
    }
}
