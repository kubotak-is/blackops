<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop;

use BlackOps\Internal\Transaction\TransactionRuntimeAccessor;
use Ray\Aop\Bind;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final readonly class AopRuntimeBindingRegistrar
{
    private const FOUNDATION_INTERCEPTOR_SERVICE = '.blackops.aop.foundation_interceptor';
    private const AFTER_COMMIT_INTERCEPTOR_SERVICE = '.blackops.aop.after_commit_interceptor';

    public function register(ContainerBuilder $builder, Definition $definition, Bind $bind): void
    {
        if (!$builder->hasDefinition(self::FOUNDATION_INTERCEPTOR_SERVICE)) {
            $builder->register(self::FOUNDATION_INTERCEPTOR_SERVICE, FoundationMethodInterceptor::class);
        }

        if (!$builder->hasDefinition(TransactionRuntimeAccessor::class)) {
            $builder->register(TransactionRuntimeAccessor::class)->setPublic(true);
        }

        if (!$builder->hasDefinition(self::AFTER_COMMIT_INTERCEPTOR_SERVICE)) {
            $builder
                ->register(self::AFTER_COMMIT_INTERCEPTOR_SERVICE, AfterCommitMethodInterceptor::class)
                ->setArguments([new Reference(TransactionRuntimeAccessor::class)]);
        }

        $runtimeBindings = [];

        foreach ($bind->getBindings() as $method => $interceptors) {
            $runtimeBindings[$method] = [];

            foreach ($interceptors as $interceptor) {
                $runtimeBindings[$method][] = match (true) {
                    $interceptor instanceof TransactionalBindingInterceptor => new Reference($this->transactional(
                        $builder,
                        $interceptor->connectionName,
                    )),
                    $interceptor instanceof AfterCommitBindingInterceptor
                        => new Reference(self::AFTER_COMMIT_INTERCEPTOR_SERVICE),
                    default => new Reference(self::FOUNDATION_INTERCEPTOR_SERVICE),
                };
            }
        }

        $definition->addMethodCall('_setBindings', [$runtimeBindings]);
    }

    private function transactional(ContainerBuilder $builder, string $connectionName): string
    {
        $id = '.blackops.aop.transactional.' . hash('sha256', $connectionName);

        if (!$builder->hasDefinition($id)) {
            $builder
                ->register($id, TransactionalMethodInterceptor::class)
                ->setArguments([new Reference(TransactionRuntimeAccessor::class), $connectionName]);
        }

        return $id;
    }
}
