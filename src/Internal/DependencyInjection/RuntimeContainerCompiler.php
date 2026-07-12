<?php

declare(strict_types=1);

namespace BlackOps\Internal\DependencyInjection;

use BlackOps\Core\DependencyInjection\ServiceProvider;
use BlackOps\Core\Registry\OperationRegistry;
use Psr\Container\ContainerInterface;
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
            if (
                $builder->has($operation->handler)
                || $builder->hasDefinition($operation->handler)
                || $builder->hasAlias($operation->handler)
            ) {
                continue;
            }

            $builder->register($operation->handler)->setAutowired(true)->setPublic(true);
        }
    }
}
