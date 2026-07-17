<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final readonly class AopMethodValidator
{
    /** @param ReflectionClass<object> $class */
    public function assertInterceptable(ReflectionClass $class, ReflectionMethod $method, string $attribute): void
    {
        if ($method->isConstructor() || $method->isDestructor()) {
            throw $this->invalid($class, $method, $attribute . ' cannot target a constructor or destructor');
        }

        if (!$method->isPublic()) {
            throw $this->invalid($class, $method, $attribute . ' requires a public method');
        }

        if ($method->isStatic()) {
            throw $this->invalid($class, $method, $attribute . ' requires an instance method');
        }

        if ($method->isFinal()) {
            throw $this->invalid($class, $method, $attribute . ' method must not be final');
        }
    }

    /** @param ReflectionClass<object> $class */
    public function assertAfterCommitSignature(ReflectionClass $class, ReflectionMethod $method): void
    {
        if ($method->isGenerator()) {
            throw $this->invalid($class, $method, 'AfterCommit cannot be a generator');
        }

        $return = $method->getReturnType();

        if (!$return instanceof ReflectionNamedType || $return->getName() !== 'void') {
            throw $this->invalid($class, $method, 'AfterCommit requires an explicit void return type');
        }

        if ($method->returnsReference()) {
            throw $this->invalid($class, $method, 'AfterCommit cannot return by reference');
        }

        foreach ($method->getParameters() as $parameter) {
            if ($parameter->isPassedByReference()) {
                throw $this->invalid($class, $method, 'AfterCommit cannot accept reference parameters');
            }
        }
    }

    public function isClassLevelCandidate(ReflectionMethod $method): bool
    {
        return $method->isPublic()
        && !$method->isStatic()
        && !$method->isFinal()
        && !$method->isConstructor()
        && !$method->isDestructor();
    }

    /** @param ReflectionClass<object> $class */
    private function invalid(ReflectionClass $class, ReflectionMethod $method, string $reason): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf(
            'AOP target method %s::%s() is invalid: %s.',
            $class->getName(),
            $method->getName(),
            $reason,
        ));
    }
}
