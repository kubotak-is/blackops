<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Auth\Session;

use BlackOps\Auth\Session\BearerSessionAuthenticator;
use BlackOps\Auth\Session\CookieSessionAuthenticator;
use BlackOps\Auth\Session\SessionConfiguration;
use BlackOps\Auth\Session\SessionIdentityProvider;
use BlackOps\Auth\Session\SessionManager;
use BlackOps\Auth\Session\SessionServiceProvider;
use BlackOps\Core\ActorRef;
use BlackOps\Database\DatabaseManager;
use BlackOps\Http\Authentication\HttpAuthenticator;
use BlackOps\Internal\DependencyInjection\RuntimeContainerCompiler;
use BlackOps\Internal\DependencyInjection\RuntimeContainerDumper;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class SessionServiceProviderTest extends TestCase
{
    public function testSessionCapabilityIsAbsentWithoutExplicitProvider(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $compiler->registerDatabaseServices($builder);
        $container = $compiler->compile($builder);

        self::assertFalse($container->has(SessionManager::class));
        self::assertFalse($container->has(HttpAuthenticator::class));
    }

    public function testBearerRegistrationResolvesOnlyPublicSessionContracts(): void
    {
        $container = $this->container(SessionServiceProvider::bearer(
            CompiledIdentityProvider::class,
            new SessionConfiguration(3_600, 60),
        ));

        self::assertInstanceOf(SessionManager::class, $container->get(SessionManager::class));
        self::assertInstanceOf(BearerSessionAuthenticator::class, $container->get(HttpAuthenticator::class));
        self::assertFalse($container->has(CookieSessionAuthenticator::class));
    }

    public function testCookieRegistrationBindsApplicationOwnedCookieName(): void
    {
        $container = $this->container(SessionServiceProvider::cookie(
            CompiledIdentityProvider::class,
            'application_session',
        ));

        self::assertInstanceOf(CookieSessionAuthenticator::class, $container->get(HttpAuthenticator::class));
        self::assertFalse($container->has(BearerSessionAuthenticator::class));
    }

    public function testRegistrationCanBeDumpedAndLoadedAsCompiledContainer(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $compiler->apply($builder, [SessionServiceProvider::bearer(CompiledIdentityProvider::class)]);
        $compiler->registerDatabaseServices($builder);
        $compiler->compile($builder);
        $path = sys_get_temp_dir() . '/blackops-session-container-' . bin2hex(random_bytes(8)) . '.php';

        try {
            new RuntimeContainerDumper()->dump($builder, $path, 'P18006SessionContainer');
            require $path;
            $class = 'P18006SessionContainer';
            /** @var ContainerInterface $container */
            $container = new $class();
            if (method_exists($container, 'set')) {
                $container->set(DatabaseManager::class, new NullDatabaseManager());
            }
            self::assertInstanceOf(BearerSessionAuthenticator::class, $container->get(HttpAuthenticator::class));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function container(SessionServiceProvider $provider): ContainerInterface
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $compiler->apply($builder, [$provider]);
        $compiler->registerDatabaseServices($builder);
        $container = $compiler->compile($builder);
        $container->set(DatabaseManager::class, new NullDatabaseManager());

        return $container;
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class CompiledIdentityProvider implements SessionIdentityProvider
{
    public function resolve(string $identityId): ?ActorRef
    {
        return new ActorRef($identityId, 'user');
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class NullDatabaseManager implements DatabaseManager
{
    public function connection(?string $name = null): Connection
    {
        throw new \LogicException('Database is not used while resolving session services.');
    }
}
