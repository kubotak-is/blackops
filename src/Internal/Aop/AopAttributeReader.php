<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop;

use InvalidArgumentException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

final readonly class AopAttributeReader
{
    /**
     * @template T of object
     * @param ReflectionClass<object>|ReflectionMethod $reflection
     * @param class-string<T> $attribute
     * @return T|null
     */
    public function read(ReflectionClass|ReflectionMethod $reflection, string $attribute): ?object
    {
        $attributes = $reflection->getAttributes($attribute, ReflectionAttribute::IS_INSTANCEOF);

        if ($attributes === []) {
            return null;
        }

        $location = $this->location($reflection);

        if (count($attributes) !== 1) {
            throw new InvalidArgumentException(sprintf('AOP attribute must not be repeated on %s.', $location));
        }

        try {
            return $attributes[0]->newInstance();
        } catch (Throwable) {
            throw new InvalidArgumentException(sprintf('Invalid AOP attribute on %s.', $location));
        }
    }

    private function location(ReflectionClass|ReflectionMethod $reflection): string
    {
        return $reflection instanceof ReflectionMethod
            ? $reflection->getDeclaringClass()->getName() . '::' . $reflection->getName() . '()'
            : $reflection->getName();
    }
}
