<?php

declare(strict_types=1);

namespace App\Http;

use App\Identity\PasswordHasher;
use App\Identity\SessionSettings;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class ApplicationHttpHandlerFactory
{
    /** @param array<string, string> $environment */
    public static function create(RequestHandlerInterface $blackOps, array $environment): RequestHandlerInterface
    {
        return new AuthenticationRouter(
            blackOps: $blackOps,
            connections: DatabaseConnectionFactory::fromEnvironment($environment),
            passwords: new PasswordHasher(),
            sessions: SessionSettings::fromEnvironment($environment),
        );
    }
}
