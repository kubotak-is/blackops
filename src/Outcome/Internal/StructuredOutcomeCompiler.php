<?php

declare(strict_types=1);

namespace BlackOps\Outcome\Internal;

use BlackOps\Core\Attribute\ListOf;
use BlackOps\Core\Attribute\Sensitive;
use BlackOps\Core\Outcome;
use BlackOps\Core\OutcomeData;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:no-boolean-flag-parameter
 */
final readonly class StructuredOutcomeCompiler
{
    public function compile(string $class): StructuredOutcomeShape
    {
        if (!class_exists($class) || !is_subclass_of($class, Outcome::class)) {
            throw new InvalidArgumentException('Structured outcome root must implement Outcome.');
        }

        return $this->compileObject($class, false, [], $class);
    }

    /**
     * @param class-string $class
     * @param list<class-string> $stack
     */
    private function compileObject(string $class, bool $dto, array $stack, string $path): StructuredOutcomeShape
    {
        if (in_array($class, $stack, strict: true)) {
            throw new InvalidArgumentException(sprintf('Structured outcome cycle detected at %s.', $path));
        }

        $reflection = new ReflectionClass($class);
        if ($dto) {
            $this->assertDto($reflection, $path);
        }

        $stack[] = $class;
        $properties = $reflection->getProperties();
        usort($properties, static fn(ReflectionProperty $left, ReflectionProperty $right): int => strcmp(
            $left->getName(),
            $right->getName(),
        ));

        $fields = [];
        foreach ($properties as $property) {
            if (!$property->isPublic() || $property->isStatic()) {
                throw new InvalidArgumentException(sprintf(
                    'Structured outcome field %s.%s must be a public instance property.',
                    $path,
                    $property->getName(),
                ));
            }
            if ($dto && !$property->isPromoted()) {
                throw new InvalidArgumentException(sprintf(
                    'Structured outcome DTO field %s.%s must be a public promoted instance property.',
                    $path,
                    $property->getName(),
                ));
            }

            $fields[] = $this->compileField($property, $stack, $path . '.' . $property->getName());
        }

        $this->assertConstructor($reflection, $fields, $dto, $path);

        return new StructuredOutcomeShape($class, $fields);
    }

    /** @param list<class-string> $stack */
    private function compileField(ReflectionProperty $property, array $stack, string $path): StructuredOutcomeField
    {
        $type = $property->getType();
        if (!$type instanceof ReflectionNamedType) {
            throw new InvalidArgumentException(sprintf('Structured outcome field %s has an unsupported type.', $path));
        }

        $listAttributes = $property->getAttributes(ListOf::class);
        if ($type->getName() === 'array') {
            if ($type->allowsNull() || count($listAttributes) !== 1) {
                throw new InvalidArgumentException(sprintf(
                    'Structured outcome list %s must be a non-nullable array with one ListOf attribute.',
                    $path,
                ));
            }

            $list = $listAttributes[0]->newInstance();
            $element = $this->compileDtoClass($list->type, $stack, $path . '[]');

            return new StructuredOutcomeField(
                $property->getName(),
                'list',
                false,
                $element,
                $property->getAttributes(Sensitive::class) !== [],
            );
        }

        if ($listAttributes !== []) {
            throw new InvalidArgumentException(sprintf('ListOf may only target an array field at %s.', $path));
        }

        if ($type->isBuiltin()) {
            $kind = match ($type->getName()) {
                'string' => 'string',
                'int' => 'integer',
                'float' => 'float',
                'bool' => 'boolean',
                default => throw new InvalidArgumentException(sprintf(
                    'Structured outcome field %s has an unsupported native type.',
                    $path,
                )),
            };

            return new StructuredOutcomeField(
                $property->getName(),
                $kind,
                $type->allowsNull(),
                sensitive: $property->getAttributes(Sensitive::class) !== [],
            );
        }

        $nested = $this->compileDtoClass($type->getName(), $stack, $path);

        return new StructuredOutcomeField(
            $property->getName(),
            'dto',
            $type->allowsNull(),
            $nested,
            $property->getAttributes(Sensitive::class) !== [],
        );
    }

    /** @param list<class-string> $stack */
    private function compileDtoClass(string $class, array $stack, string $path): StructuredOutcomeShape
    {
        if (!class_exists($class) || !is_subclass_of($class, OutcomeData::class)) {
            throw new InvalidArgumentException(sprintf(
                'Structured outcome DTO %s must be a concrete OutcomeData class.',
                $path,
            ));
        }

        return $this->compileObject($class, true, $stack, $path);
    }

    /** @param ReflectionClass<object> $reflection */
    private function assertDto(ReflectionClass $reflection, string $path): void
    {
        if (!$reflection->isInstantiable() || !$reflection->isFinal() || !$reflection->isReadOnly()) {
            throw new InvalidArgumentException(sprintf(
                'Structured outcome DTO %s must be a concrete final readonly class.',
                $path,
            ));
        }
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @param list<StructuredOutcomeField> $fields
     */
    private function assertConstructor(ReflectionClass $reflection, array $fields, bool $dto, string $path): void
    {
        $parameters = $reflection->getConstructor()?->getParameters() ?? [];
        if (
            $dto
            && array_any($parameters, static fn(\ReflectionParameter $parameter): bool => !$parameter->isPromoted())
        ) {
            throw new InvalidArgumentException(sprintf(
                'Structured outcome DTO constructor at %s must contain only promoted fields.',
                $path,
            ));
        }

        $expected = array_map(static fn(StructuredOutcomeField $field): string => $field->name, $fields);
        $actual = array_map(static fn(\ReflectionParameter $parameter): string => $parameter->getName(), $parameters);
        sort($actual);
        if ($actual !== $expected) {
            throw new InvalidArgumentException(sprintf(
                'Structured outcome constructor at %s must match its public fields.',
                $path,
            ));
        }
    }
}
