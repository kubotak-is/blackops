<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop;

use BlackOps\Database\Attribute\Transactional;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;

final readonly class AopConnectionValidator
{
    /** @param ReflectionClass<object> $class */
    public function assertKnown(
        Transactional $transactional,
        ReflectionClass $class,
        ?ReflectionMethod $method,
        AopCompilationContext $context,
    ): void {
        $connection = $transactional->connection ?? $context->defaultConnection;

        if ($connection === null) {
            throw new InvalidArgumentException(
                'AOP transaction attributes require application database configuration.',
            );
        }

        if (($context->connectionNames[$connection] ?? null) === true) {
            return;
        }

        $location = $method === null ? $class->getName() : $class->getName() . '::' . $method->getName() . '()';

        throw new InvalidArgumentException(sprintf(
            'AOP target %s references unknown database connection "%s".',
            $location,
            $connection,
        ));
    }
}
