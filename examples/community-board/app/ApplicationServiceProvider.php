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
use App\Identity\DoctrineIdentityRepository;
use App\Identity\IdentityClock;
use App\Identity\IdentityRepository;
use App\Identity\SessionToken;
use App\Identity\SystemIdentityClock;
use App\Infrastructure\Clock\SystemBoardClock;
use App\Infrastructure\Deferred\FailFirstDigestAttemptGate;
use App\Infrastructure\Deferred\NoOpDigestAttemptGate;
use App\Infrastructure\Identifier\SymfonyBoardIdGenerator;
use App\Infrastructure\Persistence\DoctrineBoardRepository;
use App\Infrastructure\Persistence\DoctrineDigestRepository;
use App\Security\BoardOperationStatusAuthorizer;
use App\Security\SessionHttpAuthenticator;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Http\Authentication\HttpAuthenticator;
use BlackOps\Status\OperationStatusAuthorizer;
use InvalidArgumentException;

final readonly class ApplicationServiceProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(BoardRepository::class, DoctrineBoardRepository::class);
        $services->autowire(BoardService::class);
        $services->autowire(DigestRepository::class, DoctrineDigestRepository::class);
        $services->autowire(DigestService::class);
        $services->autowire(BoardClock::class, SystemBoardClock::class);
        $services->autowire(BoardIdGenerator::class, SymfonyBoardIdGenerator::class);
        $services->autowire(IdentityRepository::class, DoctrineIdentityRepository::class);
        $services->autowire(IdentityClock::class, SystemIdentityClock::class);
        $services->autowire(SessionToken::class);
        $services->autowire(HttpAuthenticator::class, SessionHttpAuthenticator::class);
        $services->autowire(OperationStatusAuthorizer::class, BoardOperationStatusAuthorizer::class);
        $gate = $this->digestAttemptGate();
        $services->autowire(DigestAttemptGate::class, $gate::class);
    }

    private function digestAttemptGate(): DigestAttemptGate
    {
        return match ($_ENV['DIGEST_FAIL_FIRST_ATTEMPT'] ?? 'false') {
            'false' => new NoOpDigestAttemptGate(),
            'true' => new FailFirstDigestAttemptGate(),
            default => throw new InvalidArgumentException(
                'DIGEST_FAIL_FIRST_ATTEMPT must be the canonical value true or false.',
            ),
        };
    }
}
