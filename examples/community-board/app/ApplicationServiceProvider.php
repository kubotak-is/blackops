<?php

declare(strict_types=1);

namespace App;

use App\Domain\Board\BoardClock;
use App\Domain\Board\BoardIdGenerator;
use App\Domain\Board\BoardRepository;
use App\Domain\Board\BoardService;
use App\Identity\DoctrineIdentityRepository;
use App\Identity\IdentityClock;
use App\Identity\IdentityRepository;
use App\Identity\SessionToken;
use App\Identity\SystemIdentityClock;
use App\Infrastructure\Clock\SystemBoardClock;
use App\Infrastructure\Identifier\SymfonyBoardIdGenerator;
use App\Infrastructure\Persistence\DoctrineBoardRepository;
use App\Security\SessionHttpAuthenticator;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Http\Authentication\HttpAuthenticator;

final readonly class ApplicationServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(BoardRepository::class, DoctrineBoardRepository::class);
        $services->autowire(BoardService::class);
        $services->autowire(BoardClock::class, SystemBoardClock::class);
        $services->autowire(BoardIdGenerator::class, SymfonyBoardIdGenerator::class);
        $services->autowire(IdentityRepository::class, DoctrineIdentityRepository::class);
        $services->autowire(IdentityClock::class, SystemIdentityClock::class);
        $services->autowire(SessionToken::class);
        $services->autowire(HttpAuthenticator::class, SessionHttpAuthenticator::class);
    }
}
