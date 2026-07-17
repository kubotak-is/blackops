<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop;

use Ray\Aop\Compiler;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final readonly class AopServiceDefinitionCompiler
{
    public function __construct(
        private AopBindingFactory $bindings = new AopBindingFactory(),
        private AopClassValidator $classes = new AopClassValidator(),
        private AopRuntimeBindingRegistrar $runtimeBindings = new AopRuntimeBindingRegistrar(),
    ) {}

    public function compile(
        ContainerBuilder $builder,
        string $id,
        Definition $definition,
        AopCompilationContext $context,
    ): ?string {
        if ($definition->isSynthetic()) {
            return null;
        }

        $class = $this->class($id, $definition);

        if ($class === null) {
            return null;
        }

        $reflection = new ReflectionClass($class);
        $bind = $this->bindings->create($reflection, $context);

        if ($bind === null) {
            return null;
        }

        $this->classes->assertInterceptable($reflection);
        $directory = $context->directory;

        if ($directory === '') {
            throw new \LogicException('AOP artifact directory must not be empty.');
        }

        $proxyClass = new Compiler($directory)->compile($class, $bind);
        $this->classes->assertProxyGenerated($reflection, $class, $proxyClass);
        $proxyFile =
            $context->directory
            . DIRECTORY_SEPARATOR
            . str_replace(search: '\\', replace: '_', subject: $proxyClass)
            . '.php';
        $this->classes->assertProxyFile($reflection, $proxyFile);
        $definition->setClass($proxyClass);
        $this->runtimeBindings->register($builder, $definition, $bind);

        return $proxyFile;
    }

    /** @return class-string|null */
    private function class(string $id, Definition $definition): ?string
    {
        $class = $definition->getClass();

        if ($class === null && class_exists($id)) {
            return $id;
        }

        if ($class === null || str_contains($class, '%') || !class_exists($class)) {
            return null;
        }

        return $class;
    }
}
