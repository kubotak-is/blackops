<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use BlackOps\Core\Outcome;
use BlackOps\Outcome\Internal\StructuredOutcomeValueCodec;
use ReflectionClass;
use RuntimeException;

final readonly class PostgreSqlJournalValueCodec
{
    private const CLASS_NAME = '__class';

    public function __construct(
        private PostgreSqlJson $json = new PostgreSqlJson(),
        private StructuredOutcomeValueCodec $outcomes = new StructuredOutcomeValueCodec(),
    ) {}

    /**
     * @return array<array-key, mixed>
     */
    public function encode(object $value): array
    {
        if ($value instanceof Outcome) {
            return $this->outcomes->encode($value);
        }

        $properties = [];

        foreach (new ReflectionClass($value)->getProperties() as $property) {
            if (!$property->isPublic()) {
                continue;
            }

            $properties[$property->getName()] = $this->encodeField($property->getValue($value));
        }

        return [self::CLASS_NAME => $value::class, 'properties' => $properties];
    }

    public function decode(array $value): object
    {
        $class = $this->json->string($value, self::CLASS_NAME);

        if (!class_exists($class)) {
            throw new RuntimeException('Stored value type is invalid.');
        }

        if (is_subclass_of($class, Outcome::class)) {
            return $this->outcomes->decode($value);
        }

        return $this->decodeObject($class, $this->json->array($value, 'properties'));
    }

    private function encodeField(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        throw new RuntimeException('Stored value contains an unsupported field.');
    }

    private function decodeField(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        throw new RuntimeException('Stored value contains an unsupported field.');
    }

    /**
     * @param class-string $class
     * @param array<array-key, mixed> $properties
     */
    private function decodeObject(string $class, array $properties): object
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return $reflection->newInstance();
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $properties)) {
                $arguments[] = $this->decodeField($properties[$name]);
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            throw new RuntimeException('Stored value is missing a constructor property.');
        }

        $object = $reflection->newInstanceArgs($arguments);

        if (!is_object($object)) {
            throw new RuntimeException('Stored value could not be restored.');
        }

        return $object;
    }
}
