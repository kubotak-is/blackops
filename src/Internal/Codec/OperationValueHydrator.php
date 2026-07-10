<?php

declare(strict_types=1);

namespace BlackOps\Internal\Codec;

use BlackOps\Core\Codec\OperationCodecException;
use BlackOps\Core\OperationValue;
use ReflectionClass;
use ReflectionParameter;

final readonly class OperationValueHydrator
{
    public function __construct(
        private OperationValueArgumentCoercer $coercer = new OperationValueArgumentCoercer(),
    ) {}

    /**
     * @param class-string<OperationValue> $class
     * @param array<string, mixed> $payload
     */
    public function hydrate(string $class, array $payload): OperationValue
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            if ($payload !== []) {
                throw new OperationCodecException(
                    'Operation value without a constructor cannot receive payload fields.',
                );
            }

            return $reflection->newInstance();
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $arguments[] = $this->argumentFor($parameter, $payload);
        }

        $value = $reflection->newInstanceArgs($arguments);

        if (!$value instanceof OperationValue) {
            throw new OperationCodecException('Decoded value does not implement OperationValue.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function argumentFor(ReflectionParameter $parameter, array $payload): mixed
    {
        $name = $parameter->getName();

        if (!array_key_exists($name, $payload)) {
            return $this->missingArgument($parameter);
        }

        return $this->coerceArgument($parameter, $payload[$name]);
    }

    private function missingArgument(ReflectionParameter $parameter): mixed
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->allowsNull()) {
            return null;
        }

        throw new OperationCodecException('Encoded payload is missing a required value field.');
    }

    private function coerceArgument(ReflectionParameter $parameter, mixed $value): mixed
    {
        $type = $parameter->getType();

        if ($value === null) {
            return $this->coerceNull($parameter);
        }

        return $this->coercer->coerce($type, $value);
    }

    private function coerceNull(ReflectionParameter $parameter): mixed
    {
        if ($parameter->allowsNull()) {
            return null;
        }

        throw new OperationCodecException('Encoded payload contains null for a non-nullable value.');
    }
}
