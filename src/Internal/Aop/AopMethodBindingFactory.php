<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop;

use BlackOps\Core\Operation;
use BlackOps\Database\Attribute\AfterCommit;
use BlackOps\Database\Attribute\Transactional;
use Ray\Aop\Bind;
use ReflectionClass;
use ReflectionMethod;

final readonly class AopMethodBindingFactory
{
    public function __construct(
        private AopAttributeReader $attributes = new AopAttributeReader(),
        private AopMethodValidator $methods = new AopMethodValidator(),
        private AopConnectionValidator $connections = new AopConnectionValidator(),
    ) {}

    /** @param ReflectionClass<object> $class */
    public function bind(
        Bind $bind,
        ReflectionClass $class,
        ReflectionMethod $method,
        ?Transactional $classTransactional,
        AopCompilationContext $context,
    ): void {
        $transactional = $this->attributes->read($method, Transactional::class);
        $afterCommit = $this->attributes->read($method, AfterCommit::class);

        $connectionName = null;

        if ($transactional instanceof Transactional) {
            $this->methods->assertInterceptable($class, $method, 'Transactional');
            $connectionName = $this->connections->resolve($transactional, $class, $method, $context);
        }

        if ($afterCommit instanceof AfterCommit) {
            $this->methods->assertInterceptable($class, $method, 'AfterCommit');
            $this->methods->assertAfterCommitSignature($class, $method);
        }

        $usesClassTransactional = $classTransactional !== null && $this->methods->isClassLevelCandidate($method);
        $methodName = $method->getName();

        if ($methodName === '') {
            throw new \LogicException('AOP method name must not be empty.');
        }

        if ($transactional instanceof Transactional || $usesClassTransactional) {
            if ($connectionName === null && $classTransactional instanceof Transactional) {
                $connectionName = $this->connections->resolve($classTransactional, $class, null, $context);
            }

            $interceptor = is_a($class->getName(), Operation::class, allow_string: true)
                ? new FoundationMethodInterceptor()
                : new TransactionalBindingInterceptor(
                    $connectionName ?? throw new \LogicException(
                        'Transactional AOP binding requires a resolved connection name.',
                    ),
                );
            $bind->bindInterceptors($methodName, [$interceptor]);
        }

        if ($afterCommit instanceof AfterCommit) {
            $bind->bindInterceptors($methodName, [new AfterCommitBindingInterceptor()]);
        }
    }
}
