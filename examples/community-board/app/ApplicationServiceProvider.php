<?php

declare(strict_types=1);

namespace App;

use App\Domain\Board\BoardClock;
use App\Domain\Board\BoardIdGenerator;
use App\Domain\Board\BoardRepository;
use App\Domain\Board\BoardService;
use App\Domain\Board\DigestRepository;
use App\Domain\Board\DigestService;
use App\Feature\Digest\DigestAttemptGate;
use App\Infrastructure\Clock\SystemBoardClock;
use App\Infrastructure\Deferred\FailFirstDigestAttemptGate;
use App\Infrastructure\Deferred\NoOpDigestAttemptGate;
use App\Infrastructure\Identifier\SymfonyBoardIdGenerator;
use App\Infrastructure\Persistence\DoctrineBoardRepository;
use App\Infrastructure\Persistence\DoctrineDigestRepository;
use App\Security\BoardOperationStatusAuthorizer;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Status\OperationStatusAuthorizer;

final readonly class ApplicationServiceProvider implements ServiceProvider
{
    public function __construct(
        private bool $failFirstDigestAttempt,
    ) {}

    public function register(ServiceRegistry $services): void
    {
        $services->autowire(BoardRepository::class, DoctrineBoardRepository::class);
        $services->autowire(BoardService::class);
        $services->autowire(DigestRepository::class, DoctrineDigestRepository::class);
        $services->autowire(DigestService::class);
        $services->autowire(BoardClock::class, SystemBoardClock::class);
        $services->autowire(BoardIdGenerator::class, SymfonyBoardIdGenerator::class);
        $services->autowire(OperationStatusAuthorizer::class, BoardOperationStatusAuthorizer::class);
        $services->autowire(
            DigestAttemptGate::class,
            $this->failFirstDigestAttempt ? FailFirstDigestAttemptGate::class : NoOpDigestAttemptGate::class,
        );
    }
}
