<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop;

use BlackOps\Database\Attribute\AfterCommit;
use BlackOps\Database\Attribute\Transactional;
use InvalidArgumentException;
use ReflectionClass;

final readonly class AopAttributeTargetValidator
{
    /** @param ReflectionClass<object> $class */
    public function assertSupported(ReflectionClass $class): void
    {
        if ($class->getAttributes(AfterCommit::class) !== []) {
            throw new InvalidArgumentException(sprintf(
                'AOP target class %s cannot declare AfterCommit.',
                $class->getName(),
            ));
        }

        $this->assertProperties($class);
        $this->assertParameters($class);
    }

    /** @param ReflectionClass<object> $class */
    private function assertProperties(ReflectionClass $class): void
    {
        foreach ($class->getProperties() as $property) {
            if (
                $property->getAttributes(Transactional::class) === []
                && $property->getAttributes(AfterCommit::class) === []
            ) {
                continue;
            }

            throw new InvalidArgumentException(sprintf(
                'AOP attribute cannot target property %s::$%s.',
                $class->getName(),
                $property->getName(),
            ));
        }
    }

    /** @param ReflectionClass<object> $class */
    private function assertParameters(ReflectionClass $class): void
    {
        foreach ($class->getMethods() as $method) {
            foreach ($method->getParameters() as $parameter) {
                if (
                    $parameter->getAttributes(Transactional::class) === []
                    && $parameter->getAttributes(AfterCommit::class) === []
                ) {
                    continue;
                }

                throw new InvalidArgumentException(sprintf(
                    'AOP attribute cannot target parameter $%s of %s::%s().',
                    $parameter->getName(),
                    $class->getName(),
                    $method->getName(),
                ));
            }
        }
    }
}
