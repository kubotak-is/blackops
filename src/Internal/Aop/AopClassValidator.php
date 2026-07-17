<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop;

use InvalidArgumentException;
use ReflectionClass;

final readonly class AopClassValidator
{
    /** @param ReflectionClass<object> $class */
    public function assertInterceptable(ReflectionClass $class): void
    {
        if (!$class->isInstantiable()) {
            throw $this->invalid($class, 'must be instantiable');
        }

        if ($class->isFinal()) {
            throw $this->invalid($class, 'must not be final');
        }
    }

    /** @param ReflectionClass<object> $class */
    public function assertProxyGenerated(ReflectionClass $class, string $sourceClass, string $proxyClass): void
    {
        if ($proxyClass === $sourceClass) {
            throw $this->invalid($class, 'does not expose an interceptable attributed method');
        }
    }

    /** @param ReflectionClass<object> $class */
    public function assertProxyFile(ReflectionClass $class, string $file): void
    {
        if (!is_file($file)) {
            throw $this->invalid($class, 'proxy artifact was not generated');
        }
    }

    /** @param ReflectionClass<object> $class */
    private function invalid(ReflectionClass $class, string $reason): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf('AOP target class %s %s.', $class->getName(), $reason));
    }
}
