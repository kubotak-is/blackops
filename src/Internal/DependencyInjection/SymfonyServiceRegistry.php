<?php

declare(strict_types=1);

namespace BlackOps\Internal\DependencyInjection;

use BlackOps\Core\DependencyInjection\ServiceRegistry;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final readonly class SymfonyServiceRegistry implements ServiceRegistry
{
    public function __construct(
        private ContainerBuilder $builder,
    ) {}

    public function autowire(string $id, ?string $class = null): void
    {
        $this->builder->register($id, $class)->setAutowired(true)->setPublic(true);
    }

    public function set(string $id, object $service): void
    {
        $this->builder->set($id, $service);
    }
}
