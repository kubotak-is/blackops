<?php

declare(strict_types=1);

namespace BlackOps\Internal\DependencyInjection;

use BlackOps\Core\DependencyInjection\ServiceProvider;
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
}
