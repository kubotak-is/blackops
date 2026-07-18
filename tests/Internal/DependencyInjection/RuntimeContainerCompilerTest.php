<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\DependencyInjection;

use BlackOps\Core\Authorization\AuthorizationDecision;
use BlackOps\Core\Authorization\AuthorizationPolicy;
use BlackOps\Core\Authorization\AuthorizationRequest;
use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\DependencyInjection\ServiceRegistry;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Database\DatabaseManager;
use BlackOps\Http\Authentication\AuthenticationMiddleware;
use BlackOps\Http\Authentication\AuthenticationResult;
use BlackOps\Http\Authentication\HttpAuthenticator;
use BlackOps\Http\Console\DumpHttpManifestCommand;
use BlackOps\Http\Routing\HttpOperationManifestFile;
use BlackOps\Internal\DependencyInjection\RuntimeContainerCompiler;
use BlackOps\Internal\Execution\HandlerResolver;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

final class RuntimeContainerCompilerTest extends TestCase
{
    public function testCompiledContainerCanResolveAutowiredHandler(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $builder->register(ContainerDependency::class)->setAutowired(true)->setPublic(true);
        $builder->register(ContainerHandler::class)->setAutowired(true)->setPublic(true);

        $container = $compiler->compile($builder);
        $handler = new HandlerResolver($container)->resolve(ContainerHandler::class);

        self::assertInstanceOf(ContainerHandler::class, $handler);
        self::assertSame('dependency-ready', $handler->dependency->value);
    }

    public function testCompiledContainerCanResolveHttpManifestCommand(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $definition = new ContainerOperation();
        $builder->set('operation.registry', new OperationRegistry([$this->metadata()]));
        $builder->set('operation.definitions', new ContainerDefinitions($definition));
        $builder->register(HttpOperationManifestFile::class)->setPublic(true);
        $builder
            ->register(DumpHttpManifestCommand::class)
            ->setArguments([
                new Reference('operation.registry'),
                new Reference('operation.definitions'),
                new Reference(HttpOperationManifestFile::class),
            ])
            ->setPublic(true);

        $command = $compiler->compile($builder)->get(DumpHttpManifestCommand::class);

        self::assertInstanceOf(DumpHttpManifestCommand::class, $command);
    }

    public function testRegistersRegistryHandlersOnceAndAutowiresDependencies(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $builder->register(ContainerDependency::class)->setAutowired(true);
        $registry = new OperationRegistry([$this->metadata()]);
        $compiler->registerHandlers($builder, $registry);
        $compiler->registerHandlers($builder, $registry);

        $handler = $compiler->compile($builder)->get(ContainerHandler::class);

        self::assertInstanceOf(ContainerHandler::class, $handler);
        self::assertSame('dependency-ready', $handler->dependency->value);
    }

    public function testRegistersSyntheticRuntimeLoggerForAutowiredApplicationServices(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $compiler->registerHandlers($builder, new OperationRegistry([]));
        $builder->register(ContainerLoggerConsumer::class)->setAutowired(true)->setPublic(true);
        $container = $compiler->compile($builder);
        $logger = $this->createStub(LoggerInterface::class);

        self::assertInstanceOf(SymfonyContainerInterface::class, $container);
        $container->set(LoggerInterface::class, $logger);

        self::assertSame($logger, $container->get(ContainerLoggerConsumer::class)->logger);
    }

    public function testExplicitProviderInstanceBindingWinsOverAutomaticRegistration(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $expected = new ContainerHandler(new ContainerDependency('explicit'));
        $compiler->apply($builder, [new ExplicitHandlerProvider($expected)]);
        $compiler->registerHandlers($builder, new OperationRegistry([$this->metadata()]));

        $handler = $compiler->compile($builder)->get(ContainerHandler::class);

        self::assertSame($expected, $handler);
        self::assertSame('explicit', $handler->dependency->value);
    }

    public function testRegistersAuthorizationPolicyOnceAndAutowiresDependencies(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $builder->register(ContainerDependency::class)->setAutowired(true);
        $registry = new OperationRegistry([$this->metadata(ContainerAuthorizationPolicy::class)]);

        $compiler->registerAuthorizationPolicies($builder, $registry);
        $compiler->registerAuthorizationPolicies($builder, $registry);

        $policy = $compiler->compile($builder)->get(ContainerAuthorizationPolicy::class);

        self::assertInstanceOf(ContainerAuthorizationPolicy::class, $policy);
        self::assertSame('dependency-ready', $policy->dependency->value);
    }

    public function testExplicitProviderAuthorizationPolicyBindingWinsOverAutomaticRegistration(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $expected = new ContainerAuthorizationPolicy(new ContainerDependency('explicit'));
        $compiler->apply($builder, [new ExplicitAuthorizationPolicyProvider($expected)]);

        $compiler->registerAuthorizationPolicies($builder, new OperationRegistry([$this->metadata(ContainerAuthorizationPolicy::class)]));

        self::assertSame($expected, $compiler->compile($builder)->get(ContainerAuthorizationPolicy::class));
    }

    public function testApplicationProviderInterfaceBindingIsInjectedIntoSelfHandledOperation(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $registry = new OperationRegistry([new OperationMetadata(
            'container.self.handled',
            RepositoryBackedOperation::class,
            ContainerValue::class,
            RepositoryBackedOperation::class,
            EmptyOutcome::class,
            Inline::class,
            true,
        )]);
        $compiler->apply($builder, [new RepositoryBindingProvider()]);
        $compiler->registerHandlers($builder, $registry);

        $handler = $compiler->compile($builder)->get(RepositoryBackedOperation::class);

        self::assertInstanceOf(RepositoryBackedOperation::class, $handler);
        self::assertSame('repository-ready', $handler->repository->value());
    }

    public function testExplicitTypedSelfHandledBindingWinsOverAutomaticRegistration(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $expected = new RepositoryBackedOperation(new ContainerRepositoryImplementation());
        $metadata = new OperationMetadata(
            'container.self.handled.explicit',
            RepositoryBackedOperation::class,
            ContainerValue::class,
            RepositoryBackedOperation::class,
            EmptyOutcome::class,
            Inline::class,
            true,
        );
        $compiler->apply($builder, [new ExplicitTypedHandlerProvider($expected)]);
        $compiler->registerHandlers($builder, new OperationRegistry([$metadata]));

        self::assertSame($expected, $compiler->compile($builder)->get(RepositoryBackedOperation::class));
    }

    public function testRegistersMiddlewareClassAsAutowiredPublicService(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $builder->register(ContainerDependency::class)->setAutowired(true);

        $compiler->registerHttpMiddleware($builder, [ContainerMiddleware::class]);

        $middleware = $compiler->compile($builder)->get(ContainerMiddleware::class);

        self::assertInstanceOf(ContainerMiddleware::class, $middleware);
        self::assertSame('dependency-ready', $middleware->dependency->value);
    }

    public function testExplicitProviderMiddlewareBindingWinsOverAutomaticRegistration(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $expected = new ContainerMiddleware(new ContainerDependency('explicit'));
        $compiler->apply($builder, [new ExplicitMiddlewareProvider($expected)]);

        $compiler->registerHttpMiddleware($builder, [ContainerMiddleware::class]);

        self::assertSame($expected, $compiler->compile($builder)->get(ContainerMiddleware::class));
    }

    public function testRegistersSyntheticDatabaseServicesForAutowiredApplicationService(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $compiler->registerDatabaseServices($builder);
        $builder->register(ContainerDatabaseConsumer::class)->setAutowired(true)->setPublic(true);
        $container = $compiler->compile($builder);
        $connection = $this->createStub(Connection::class);
        $databases = $this->createStub(DatabaseManager::class);

        self::assertInstanceOf(SymfonyContainerInterface::class, $container);
        $container->set(DatabaseManager::class, $databases);
        $container->set(Connection::class, $connection);
        $consumer = $container->get(ContainerDatabaseConsumer::class);

        self::assertInstanceOf(ContainerDatabaseConsumer::class, $consumer);
        self::assertSame($databases, $consumer->databases);
        self::assertSame($connection, $consumer->connection);
    }

    public function testRejectsProviderDatabaseRuntimeServiceRedefinitionWithoutOverwritingIt(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $expected = $this->createStub(DatabaseManager::class);
        $compiler->apply($builder, [new ExplicitDatabaseManagerProvider($expected)]);

        try {
            $compiler->registerDatabaseServices($builder);
            self::fail('Expected database runtime service redefinition rejection.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame($expected, $builder->get(DatabaseManager::class));
            self::assertStringNotContainsString('credential', $exception->getMessage());
        }
    }

    public function testAutowiresAuthenticationMiddlewareWithProviderAuthenticatorAndDefaultPsr17Factories(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();
        $compiler->apply($builder, [new HttpAuthenticatorProvider()]);

        $compiler->registerHttpMiddleware($builder, [AuthenticationMiddleware::class]);

        self::assertInstanceOf(
            AuthenticationMiddleware::class,
            $compiler->compile($builder)->get(AuthenticationMiddleware::class),
        );
    }

    public function testRejectsUnavailableOrNonMiddlewareClassRegistration(): void
    {
        $compiler = new RuntimeContainerCompiler();
        $builder = $compiler->builder();

        try {
            $compiler->registerHttpMiddleware($builder, ['Missing\\CredentialService']);
            self::fail('Expected unavailable middleware rejection.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringNotContainsString('Missing\\CredentialService', $exception->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);

        $compiler->registerHttpMiddleware($builder, [ContainerDependency::class]);
    }

    /** @param class-string<AuthorizationPolicy>|null $authorizationPolicy */
    private function metadata(?string $authorizationPolicy = null): OperationMetadata
    {
        return new OperationMetadata(
            'container.operation',
            ContainerOperation::class,
            ContainerValue::class,
            ContainerHandler::class,
            EmptyOutcome::class,
            Inline::class,
            authorizationPolicy: $authorizationPolicy,
        );
    }
}

final readonly class ContainerDependency
{
    public function __construct(
        public string $value = 'dependency-ready',
    ) {}
}

final readonly class ContainerOperation implements Operation {}

/**
 * @implements \IteratorAggregate<int, Operation>
 */
final readonly class ContainerDefinitions implements \IteratorAggregate
{
    public function __construct(
        private Operation $definition,
    ) {}

    public function getIterator(): \Traversable
    {
        yield $this->definition;
    }
}

final readonly class ContainerValue implements OperationValue {}

final readonly class ContainerHandler implements OperationHandler
{
    public function __construct(
        public ContainerDependency $dependency,
    ) {}

    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::completed();
    }
}

final readonly class ExplicitHandlerProvider implements ServiceProvider
{
    public function __construct(
        private ContainerHandler $handler,
    ) {}

    public function register(ServiceRegistry $services): void
    {
        $services->set(ContainerHandler::class, $this->handler);
    }
}

final readonly class ContainerAuthorizationPolicy implements AuthorizationPolicy
{
    public function __construct(
        public ContainerDependency $dependency,
    ) {}

    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        return AuthorizationDecision::allow();
    }
}

final readonly class ExplicitAuthorizationPolicyProvider implements ServiceProvider
{
    public function __construct(
        private ContainerAuthorizationPolicy $policy,
    ) {}

    public function register(ServiceRegistry $services): void
    {
        $services->set(ContainerAuthorizationPolicy::class, $this->policy);
    }
}

interface ContainerRepository
{
    public function value(): string;
}

final readonly class ContainerRepositoryImplementation implements ContainerRepository
{
    public function value(): string
    {
        return 'repository-ready';
    }
}

final readonly class RepositoryBindingProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(ContainerRepository::class, ContainerRepositoryImplementation::class);
    }
}

final readonly class ExplicitTypedHandlerProvider implements ServiceProvider
{
    public function __construct(
        private RepositoryBackedOperation $handler,
    ) {}

    public function register(ServiceRegistry $services): void
    {
        $services->set(RepositoryBackedOperation::class, $this->handler);
    }
}

final readonly class ContainerMiddleware implements MiddlewareInterface
{
    public function __construct(
        public ContainerDependency $dependency,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}

final readonly class ExplicitMiddlewareProvider implements ServiceProvider
{
    public function __construct(
        private ContainerMiddleware $middleware,
    ) {}

    public function register(ServiceRegistry $services): void
    {
        $services->set(ContainerMiddleware::class, $this->middleware);
    }
}

final readonly class HttpAuthenticatorProvider implements ServiceProvider
{
    public function register(ServiceRegistry $services): void
    {
        $services->autowire(HttpAuthenticator::class, ContainerHttpAuthenticator::class);
    }
}

final readonly class ContainerHttpAuthenticator implements HttpAuthenticator
{
    public function authenticate(ServerRequestInterface $request): AuthenticationResult
    {
        return AuthenticationResult::anonymous();
    }
}

final readonly class RepositoryBackedOperation implements Operation
{
    public function __construct(
        public ContainerRepository $repository,
    ) {}

    public function handle(ContainerValue $value): OperationResult
    {
        return OperationResult::completed();
    }
}

final readonly class ContainerDatabaseConsumer
{
    public function __construct(
        public DatabaseManager $databases,
        public Connection $connection,
    ) {}
}

final readonly class ContainerLoggerConsumer
{
    public function __construct(
        public LoggerInterface $logger,
    ) {}
}

final readonly class ExplicitDatabaseManagerProvider implements ServiceProvider
{
    public function __construct(
        private DatabaseManager $databases,
    ) {}

    public function register(ServiceRegistry $services): void
    {
        $services->set(DatabaseManager::class, $this->databases);
    }
}
