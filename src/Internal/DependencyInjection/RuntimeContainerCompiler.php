<?php

declare(strict_types=1);

namespace BlackOps\Internal\DependencyInjection;

use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Database\AfterCommitFailureReporter;
use BlackOps\Database\DatabaseManager;
use BlackOps\Internal\Transaction\DefaultAfterCommitFailureReporter;
use BlackOps\Internal\Transaction\TransactionRuntime;
use BlackOps\Internal\Transaction\TransactionRuntimeAccessor;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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
        foreach ($operations->all() as $operation) {
            if ($builder->has($operation->handler)) {
                continue;
            }

            $builder->register($operation->handler)->setAutowired(true)->setPublic(true);
        }
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
