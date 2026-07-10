<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Core\Attribute\PublicApi;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

final readonly class PublicApiArchitectureGuard
{
    private const INTERNAL_NAMESPACE = 'BlackOps\\Internal';

    /**
     * @param list<class-string> $types
     * @return list<string>
     */
    public function violations(array $types): array
    {
        $violations = [];

        foreach ($types as $type) {
            $reflection = new ReflectionClass($type);
            $isPublicApi = $reflection->getAttributes(PublicApi::class) !== [];

            if ($isPublicApi && $this->isInternalType($reflection->getName())) {
                $violations[] = sprintf(
                    '%s is internal and must not be declared as PublicApi.',
                    $reflection->getName(),
                );
            }

            if (!$isPublicApi) {
                continue;
            }

            $this->inspectInheritance($reflection, $violations);
            $this->inspectConstructor($reflection, $violations);
            $this->inspectMethods($reflection, $violations);
            $this->inspectProperties($reflection, $violations);
        }

        $violations = array_values(array_unique($violations));
        sort($violations);

        return $violations;
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @param list<string> $violations
     */
    private function inspectInheritance(ReflectionClass $reflection, array &$violations): void
    {
        $parent = $reflection->getParentClass();

        while ($parent !== false) {
            $this->recordInternalType($parent->getName(), sprintf('%s parent', $reflection->getName()), $violations);
            $parent = $parent->getParentClass();
        }

        foreach ($reflection->getInterfaceNames() as $interface) {
            $this->recordInternalType($interface, sprintf('%s interface', $reflection->getName()), $violations);
        }
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @param list<string> $violations
     */
    private function inspectConstructor(ReflectionClass $reflection, array &$violations): void
    {
        $constructor = $reflection->getConstructor();

        if ($constructor === null || !$constructor->isPublic()) {
            return;
        }

        $this->inspectMethodParameters($reflection, $constructor, $violations);
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @param list<string> $violations
     */
    private function inspectMethods(ReflectionClass $reflection, array &$violations): void
    {
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor()) {
                continue;
            }

            $location = sprintf('%s::%s() return', $reflection->getName(), $method->getName());
            $this->inspectType($method->getReturnType(), $location, $violations);
            $this->inspectMethodParameters($reflection, $method, $violations);
        }
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @param list<string> $violations
     */
    private function inspectMethodParameters(
        ReflectionClass $reflection,
        ReflectionMethod $method,
        array &$violations,
    ): void {
        foreach ($method->getParameters() as $parameter) {
            $location = sprintf(
                '%s::%s() parameter $%s',
                $reflection->getName(),
                $method->getName(),
                $parameter->getName(),
            );
            $this->inspectType($parameter->getType(), $location, $violations);
        }
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @param list<string> $violations
     */
    private function inspectProperties(ReflectionClass $reflection, array &$violations): void
    {
        foreach ($reflection->getProperties() as $property) {
            if (!$property->isPublic()) {
                continue;
            }

            $location = sprintf('%s::$%s property', $reflection->getName(), $property->getName());
            $this->inspectType($property->getType(), $location, $violations);
        }
    }

    /**
     * @param list<string> $violations
     */
    private function inspectType(?ReflectionType $type, string $location, array &$violations): void
    {
        if ($type instanceof ReflectionNamedType) {
            if (!$type->isBuiltin()) {
                $this->recordInternalType($type->getName(), $location, $violations);
            }

            return;
        }

        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            foreach ($type->getTypes() as $nestedType) {
                $this->inspectType($nestedType, $location, $violations);
            }
        }
    }

    /**
     * @param list<string> $violations
     */
    private function recordInternalType(string $type, string $location, array &$violations): void
    {
        if ($this->isInternalType($type)) {
            $violations[] = sprintf('%s exposes internal type %s.', $location, $type);
        }
    }

    private function isInternalType(string $type): bool
    {
        return $type === self::INTERNAL_NAMESPACE || str_starts_with($type, self::INTERNAL_NAMESPACE . '\\');
    }
}
