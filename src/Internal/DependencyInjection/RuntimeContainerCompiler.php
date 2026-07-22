<?php

declare(strict_types=1);

namespace BlackOps\Internal\DependencyInjection;

use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Database\AfterCommitFailureReporter;
use BlackOps\Database\DatabaseManager;
use BlackOps\Database\Seeder;
use BlackOps\Database\SeederRunner;
use BlackOps\Internal\Seeder\CompiledSeederRunner;
use BlackOps\Internal\Seeder\CompiledSeederRuntime;
use BlackOps\Internal\Transaction\DefaultAfterCommitFailureReporter;
use BlackOps\Internal\Transaction\TransactionRuntime;
use BlackOps\Internal\Transaction\TransactionRuntimeAccessor;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class RuntimeContainerCompiler
{
    public function builder(): ContainerBuilder
    {
        return new ContainerBuilder();
    }

    public function compile(ContainerBuilder $builder): ContainerInterface
    {
        $builder->compile();

        return $builder;
    }

    /**
     * @param iterable<ServiceProvider> $providers
     */
    public function apply(ContainerBuilder $builder, iterable $providers): void
    {
        $registry = new SymfonyServiceRegistry($builder);

        foreach ($providers as $provider) {
            $provider->register($registry);
        }
    }

    public function registerHandlers(ContainerBuilder $builder, OperationRegistry $operations): void
    {
        $this->registerRuntimeLogger($builder);

        foreach ($operations->all() as $operation) {
            if ($builder->has($operation->handler)) {
                continue;
            }

            $builder->register($operation->handler)->setAutowired(true)->setPublic(true);
        }
    }

    private function registerRuntimeLogger(ContainerBuilder $builder): void
    {
        if ($builder->has(LoggerInterface::class)) {
            if (
                $builder->hasDefinition(LoggerInterface::class)
                && $builder->getDefinition(LoggerInterface::class)->isSynthetic()
            ) {
                return;
            }

            throw new InvalidArgumentException('Runtime logging service cannot be redefined by a service provider.');
        }

        $builder->register(LoggerInterface::class, LoggerInterface::class)->setSynthetic(true)->setPublic(true);
    }

    public function registerDatabaseServices(ContainerBuilder $builder): void
    {
        if ($builder->has(DatabaseManager::class) || $builder->has(Connection::class)) {
            throw new InvalidArgumentException('Database runtime services cannot be redefined by a service provider.');
        }

        $builder->register(DatabaseManager::class, DatabaseManager::class)->setSynthetic(true)->setPublic(true);
        $builder->register(Connection::class, Connection::class)->setSynthetic(true)->setPublic(true);

        if (!$builder->has(AfterCommitFailureReporter::class)) {
            $builder
                ->register(AfterCommitFailureReporter::class, DefaultAfterCommitFailureReporter::class)
                ->setPublic(true);
        }

        if ($builder->has(TransactionRuntime::class) || $builder->has(TransactionRuntimeAccessor::class)) {
            throw new InvalidArgumentException(
                'Transaction runtime service cannot be redefined by a service provider.',
            );
        }

        $builder->register(TransactionRuntime::class, TransactionRuntime::class)->setSynthetic(true)->setPublic(true);
        $builder->register(TransactionRuntimeAccessor::class)->setPublic(true);
    }

    public function registerAuthorizationPolicies(ContainerBuilder $builder, OperationRegistry $operations): void
    {
        foreach ($operations->all() as $operation) {
            $policy = $operation->authorizationPolicy;

            if ($policy === null || $builder->has($policy)) {
                continue;
            }

            $builder->register($policy)->setAutowired(true)->setPublic(true);
        }
    }

    /** @param iterable<class-string<Command>> $commands */
    public function registerApplicationCommands(ContainerBuilder $builder, iterable $commands): void
    {
        foreach ($commands as $command) {
            if ($builder->has($command)) {
                continue;
            }

            $builder->register($command)->setAutowired(true)->setPublic(true);
        }
    }

    /**
     * @param list<class-string<Seeder>> $seeders
     * @param class-string<Seeder>|null $root
     */
    public function registerSeeders(ContainerBuilder $builder, array $seeders, ?string $root): void
    {
        if (
            $builder->has(SeederRunner::class)
            || $builder->has(CompiledSeederRunner::class)
            || $builder->has(CompiledSeederRuntime::class)
        ) {
            throw new InvalidArgumentException('Seeder runtime services cannot be redefined by a service provider.');
        }

        $references = [];
        foreach ($seeders as $seeder) {
            if (!is_a($seeder, Seeder::class, allow_string: true)) {
                throw new InvalidArgumentException('Discovered seeder must implement the Seeder interface.');
            }
            if (!$builder->has($seeder)) {
                $builder->register($seeder)->setAutowired(true)->setPublic(false);
            }

            $references[$seeder] = new Reference($seeder);
        }

        $builder
            ->register(CompiledSeederRunner::class)
            ->setArguments([new ServiceLocatorArgument($references)])
            ->setPublic(false);
        $builder->setAlias(SeederRunner::class, CompiledSeederRunner::class)->setPublic(false);
        $builder
            ->register(CompiledSeederRuntime::class)
            ->setArguments([new Reference(SeederRunner::class), $root])
            ->setPublic(true);
    }

    /** @param list<string> $middleware */
    public function registerHttpMiddleware(ContainerBuilder $builder, array $middleware): void
    {
        foreach ($middleware as $id) {
            if ($builder->has($id)) {
                continue;
            }

            if (!class_exists($id) || !is_a($id, MiddlewareInterface::class, allow_string: true)) {
                throw new InvalidArgumentException('Configured HTTP middleware must be a registered service or class.');
            }

            $builder->register($id)->setAutowired(true)->setPublic(true);
        }
    }
}
