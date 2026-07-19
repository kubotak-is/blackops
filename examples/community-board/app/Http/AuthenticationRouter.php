<?php

declare(strict_types=1);

namespace App\Http;

use App\Identity\DoctrineIdentityRepository;
use App\Identity\IdentityService;
use App\Identity\PasswordHasher;
use App\Identity\SessionSettings;
use App\Identity\SessionToken;
use App\Identity\SymfonyUuidV7Generator;
use App\Identity\SystemIdentityClock;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

final readonly class AuthenticationRouter implements RequestHandlerInterface
{
    public function __construct(
        private RequestHandlerInterface $blackOps,
        private DatabaseConnectionFactory $connections,
        private PasswordHasher $passwords,
        private SessionSettings $sessions,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (!$this->isAuthenticationPath($path)) {
            return $this->blackOps->handle($request);
        }

        $allowedMethod = match ($path) {
            '/auth/users', '/auth/sessions' => 'POST',
            '/auth/sessions/current' => 'DELETE',
            default => null,
        };

        if ($allowedMethod === null) {
            return JsonResponse::error(404, 'identity.route_not_found');
        }

        if (strtoupper($request->getMethod()) !== $allowedMethod) {
            return JsonResponse::error(405, 'identity.method_not_allowed', headers: ['Allow' => $allowedMethod]);
        }

        $connection = null;
        try {
            $connection = $this->connections->create();
            $handler = new AuthenticationHttpHandler(new IdentityService(
                repository: new DoctrineIdentityRepository($connection),
                passwords: $this->passwords,
                tokens: new SessionToken(),
                clock: new SystemIdentityClock(),
                identifiers: new SymfonyUuidV7Generator(),
                settings: $this->sessions,
            ));

            return match ($path) {
                '/auth/users' => $handler->register($request),
                '/auth/sessions' => $handler->login($request),
                '/auth/sessions/current' => $handler->logout($request),
            };
        } catch (Throwable) {
            return JsonResponse::error(500, 'identity.internal_error');
        } finally {
            $connection?->close();
        }
    }

    private function isAuthenticationPath(string $path): bool
    {
        return $path === '/auth' || str_starts_with($path, '/auth/');
    }
}
