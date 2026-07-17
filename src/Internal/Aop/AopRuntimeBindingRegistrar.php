<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop;

use Ray\Aop\Bind;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final readonly class AopRuntimeBindingRegistrar
{
    private const FOUNDATION_INTERCEPTOR_SERVICE = '.blackops.aop.foundation_interceptor';

    public function register(ContainerBuilder $builder, Definition $definition, Bind $bind): void
    {
        if (!$builder->hasDefinition(self::FOUNDATION_INTERCEPTOR_SERVICE)) {
            $builder->register(self::FOUNDATION_INTERCEPTOR_SERVICE, FoundationMethodInterceptor::class);
        }

        $runtimeBindings = [];

        foreach ($bind->getBindings() as $method => $interceptors) {
            $runtimeBindings[$method] = array_fill(
                start_index: 0,
                count: count($interceptors),
                value: new Reference(self::FOUNDATION_INTERCEPTOR_SERVICE),
            );
        }

        $definition->addMethodCall('_setBindings', [$runtimeBindings]);
    }
}
