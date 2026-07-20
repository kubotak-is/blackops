<?php

declare(strict_types=1);

namespace BlackOps\Outcome\Internal;

use BlackOps\Core\Outcome;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;

/**
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final readonly class StructuredOutcomeValueCodec
{
    public function __construct(
        private StructuredOutcomeCompiler $compiler = new StructuredOutcomeCompiler(),
    ) {}

    /** @return array{__class: string, properties: array<string, mixed>} */
    public function encode(Outcome $outcome): array
    {
        return $this->encodeObject($outcome, $this->compiler->compile($outcome::class), $outcome::class);
    }

    public function decode(array $encoded): Outcome
    {
        $class = $this->outcomeClass($encoded['__class'] ?? null);

        $decoded = $this->decodeObject($encoded, $this->compiler->compile($class), $class);
        if (!$decoded instanceof Outcome || $decoded::class !== $class) {
            throw new InvalidArgumentException('Stored structured outcome restored an invalid root type.');
        }

        return $decoded;
    }

    /** @return array{__class: string, properties: array<string, mixed>} */
    private function encodeObject(object $value, StructuredOutcomeShape $shape, string $path): array
    {
        if ($value::class !== $shape->class) {
            throw new InvalidArgumentException(sprintf('Structured outcome object at %s has an invalid class.', $path));
        }

        $properties = [];
        foreach ($shape->fields as $field) {
            $property = new ReflectionProperty($shape->class, $field->name);
            if (!$property->isInitialized($value)) {
                throw new InvalidArgumentException(sprintf('Structured outcome field %s is uninitialized.', $path));
            }
            $properties[$field->name] = $this->encodeField(
                $property->getValue($value),
                $field,
                $path . '.' . $field->name,
            );
        }

        return ['__class' => $shape->class, 'properties' => $properties];
    }

    private function encodeField(mixed $value, StructuredOutcomeField $field, string $path): mixed
    {
        if ($value === null) {
            if ($field->nullable) {
                return null;
            }

            $this->invalid($path);
        }

        return match ($field->kind) {
            'string' => is_string($value) ? $value : $this->invalid($path),
            'integer' => is_int($value) ? $value : $this->invalid($path),
            'float' => is_float($value) && is_finite($value) ? ['__float' => (string) $value] : $this->invalid($path),
            'boolean' => is_bool($value) ? $value : $this->invalid($path),
            'dto' => is_object($value) && $field->dto !== null
                ? $this->encodeObject($value, $field->dto, $path)
                : $this->invalid($path),
            'list' => $this->encodeList($value, $field, $path),
        };
    }

    /** @return list<array{__class: string, properties: array<string, mixed>}> */
    private function encodeList(mixed $value, StructuredOutcomeField $field, string $path): array
    {
        if (!is_array($value) || !array_is_list($value) || $field->dto === null) {
            $this->invalid($path);
        }

        $encoded = [];
        for ($index = 0; $index < count($value); ++$index) {
            if (!is_object($value[$index]) || $value[$index]::class !== $field->dto->class) {
                $this->invalid($path . '[' . $index . ']');
            }
            $encoded[] = $this->encodeObject($value[$index], $field->dto, $path . '[' . $index . ']');
        }

        return $encoded;
    }

    private function decodeObject(array $encoded, StructuredOutcomeShape $shape, string $path): object
    {
        $keys = array_keys($encoded);
        sort($keys);
        if (
            $keys !== ['__class', 'properties']
            || ($encoded['__class'] ?? null) !== $shape->class
            || !is_array($encoded['properties'] ?? null)
        ) {
            throw new InvalidArgumentException(sprintf('Stored structured outcome object at %s is invalid.', $path));
        }

        $properties = $encoded['properties'];
        $expected = array_map(static fn(StructuredOutcomeField $field): string => $field->name, $shape->fields);
        $actual = array_keys($properties);
        if (!array_all($actual, static fn(mixed $key): bool => is_string($key))) {
            throw new InvalidArgumentException(sprintf('Stored structured outcome fields at %s are invalid.', $path));
        }
        sort($actual);
        if ($actual !== $expected) {
            throw new InvalidArgumentException(sprintf('Stored structured outcome fields at %s do not match.', $path));
        }

        $values = [];
        foreach ($shape->fields as $field) {
            $values[$field->name] = $this->decodeField($properties[$field->name], $field, $path . '.' . $field->name);
        }

        $reflection = new ReflectionClass($shape->class);
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            if ($values !== []) {
                throw new InvalidArgumentException(sprintf(
                    'Stored structured outcome constructor at %s is invalid.',
                    $path,
                ));
            }

            return $reflection->newInstance();
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (array_key_exists($name, $values)) {
                $arguments[] = $values[$name];
                continue;
            }
            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }
            throw new InvalidArgumentException(sprintf(
                'Stored structured outcome constructor at %s is incomplete.',
                $path,
            ));
        }

        $object = $reflection->newInstanceArgs($arguments);
        if (!is_object($object)) {
            throw new InvalidArgumentException(sprintf(
                'Stored structured outcome constructor at %s is invalid.',
                $path,
            ));
        }

        return $object;
    }

    private function decodeField(mixed $value, StructuredOutcomeField $field, string $path): mixed
    {
        if ($value === null) {
            if ($field->nullable) {
                return null;
            }

            $this->invalid($path);
        }

        return match ($field->kind) {
            'string' => is_string($value) ? $value : $this->invalid($path),
            'integer' => is_int($value) ? $value : $this->invalid($path),
            'float' => $this->decodeFloat($value, $path),
            'boolean' => is_bool($value) ? $value : $this->invalid($path),
            'dto' => is_array($value) && $field->dto !== null
                ? $this->decodeObject($value, $field->dto, $path)
                : $this->invalid($path),
            'list' => $this->decodeList($value, $field, $path),
        };
    }

    /** @return list<object> */
    private function decodeList(mixed $value, StructuredOutcomeField $field, string $path): array
    {
        if (!is_array($value) || !array_is_list($value) || $field->dto === null) {
            $this->invalid($path);
        }

        $decoded = [];
        for ($index = 0; $index < count($value); ++$index) {
            if (!is_array($value[$index])) {
                $this->invalid($path . '[' . $index . ']');
            }
            $decoded[] = $this->decodeObject($value[$index], $field->dto, $path . '[' . $index . ']');
        }

        return $decoded;
    }

    private function invalid(string $path): never
    {
        throw new InvalidArgumentException(sprintf('Stored structured outcome value at %s is invalid.', $path));
    }

    private function decodeFloat(mixed $value, string $path): float
    {
        if (
            !is_array($value)
            || array_keys($value) !== ['__float']
            || !is_string($value['__float'])
            || !is_numeric($value['__float'])
        ) {
            $this->invalid($path);
        }

        $decoded = (float) $value['__float'];
        return is_finite($decoded) ? $decoded : $this->invalid($path);
    }

    /** @return class-string<Outcome> */
    private function outcomeClass(mixed $value): string
    {
        if (!is_string($value) || !class_exists($value) || !is_subclass_of($value, Outcome::class)) {
            throw new InvalidArgumentException('Stored structured outcome root class is invalid.');
        }

        return $value;
    }
}
