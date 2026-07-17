<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop;

use BlackOps\Database\Attribute\AfterCommit;
use BlackOps\Database\Attribute\Transactional;
use Ray\Aop\Bind;
use ReflectionClass;

final readonly class AopBindingFactory
{
    public function __construct(
        private AopAttributeReader $attributes = new AopAttributeReader(),
        private AopAttributeTargetValidator $targets = new AopAttributeTargetValidator(),
        private AopConnectionValidator $connections = new AopConnectionValidator(),
        private AopMethodBindingFactory $methods = new AopMethodBindingFactory(),
    ) {}

    /** @param ReflectionClass<object> $class */
    public function create(ReflectionClass $class, AopCompilationContext $context): ?Bind
    {
        $this->targets->assertSupported($class);
        $classTransactional = $this->attributes->read($class, Transactional::class);

        if (!$classTransactional instanceof Transactional && !$this->hasMethodAttribute($class)) {
            return null;
        }

        if ($classTransactional instanceof Transactional) {
            $this->connections->assertKnown($classTransactional, $class, null, $context);
        }

        $bind = new Bind();

        foreach ($class->getMethods() as $method) {
            $this->methods->bind($bind, $class, $method, $classTransactional, $context);
        }

        return $bind;
    }

    /** @param ReflectionClass<object> $class */
    private function hasMethodAttribute(ReflectionClass $class): bool
    {
        foreach ($class->getMethods() as $method) {
            if (
                $method->getAttributes(Transactional::class) !== []
                || $method->getAttributes(AfterCommit::class) !== []
            ) {
                return true;
            }
        }

        return false;
    }
}
