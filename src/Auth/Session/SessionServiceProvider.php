<?php

declare(strict_types=1);

namespace BlackOps\Auth\Session;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Http\Authentication\HttpAuthenticator;
use BlackOps\Internal\Auth\Session\CryptographicSessionTokenGenerator;
use BlackOps\Internal\Auth\Session\DefaultSessionManager;
use BlackOps\Internal\Auth\Session\PostgreSqlSessionStore;
use BlackOps\Internal\Auth\Session\SessionClock;
use BlackOps\Internal\Auth\Session\SessionIdentifierGenerator;
use BlackOps\Internal\Auth\Session\SessionStore;
use BlackOps\Internal\Auth\Session\SessionTokenGenerator;
use BlackOps\Internal\Auth\Session\SymfonySessionIdentifierGenerator;
use BlackOps\Internal\Auth\Session\SystemSessionClock;
use InvalidArgumentException;

#[PublicApi]
final readonly class SessionServiceProvider implements ServiceProvider
{
    private const string BEARER = 'bearer';
    private const string COOKIE = 'cookie';

    /**
     * @param class-string<SessionIdentityProvider> $identityProvider
     */
    private function __construct(
        private string $identityProvider,
        private SessionConfiguration $configuration,
        private string $adapter,
        private ?SessionCookieName $cookie,
    ) {
        if (!is_a($identityProvider, SessionIdentityProvider::class, allow_string: true)) {
            throw new InvalidArgumentException('Session identity provider must implement the public contract.');
        }
    }

    /** @param class-string<SessionIdentityProvider> $identityProvider */
    public static function bearer(string $identityProvider, ?SessionConfiguration $configuration = null): self
    {
        return new self($identityProvider, $configuration ?? new SessionConfiguration(), self::BEARER, null);
    }

    /** @param class-string<SessionIdentityProvider> $identityProvider */
    public static function cookie(
        string $identityProvider,
        string $cookieName,
        ?SessionConfiguration $configuration = null,
    ): self {
        return new self(
            $identityProvider,
            $configuration ?? new SessionConfiguration(),
            self::COOKIE,
            new SessionCookieName($cookieName),
        );
    }

    public function register(ServiceRegistry $services): void
    {
        $services->set(SessionConfiguration::class, $this->configuration);
        $services->autowire(SessionIdentityProvider::class, $this->identityProvider);
        $services->autowire(SessionClock::class, SystemSessionClock::class);
        $services->autowire(SessionTokenGenerator::class, CryptographicSessionTokenGenerator::class);
        $services->autowire(SessionIdentifierGenerator::class, SymfonySessionIdentifierGenerator::class);
        $services->autowire(SessionStore::class, PostgreSqlSessionStore::class);
        $services->autowire(SessionManager::class, DefaultSessionManager::class);

        if ($this->adapter === self::COOKIE) {
            $cookie = $this->cookie;

            if ($cookie === null) {
                throw new InvalidArgumentException('Cookie session authentication requires a cookie name.');
            }

            $services->set(SessionCookieName::class, $cookie);
            $services->autowire(HttpAuthenticator::class, CookieSessionAuthenticator::class);

            return;
        }

        $services->autowire(HttpAuthenticator::class, BearerSessionAuthenticator::class);
    }
}
