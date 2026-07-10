<?php

declare(strict_types=1);

namespace BlackOps\Internal\Codec;

use BlackOps\Core\Codec\OperationCodecException;
use BlackOps\Core\OperationValue;
use ReflectionClass;
use ReflectionProperty;

final readonly class OperationValueNormalizer
{
    /**
     * @return array<string, mixed>
     */
    public function normalize(OperationValue $value): array
    {
        $data = [];

        foreach (new ReflectionClass($value)->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isInitialized($value)) {
                throw new OperationCodecException('Operation value contains an uninitialized public property.');
            }

            $data[$property->getName()] = $this->normalizePayloadValue($property->getValue($value));
        }

        return $data;
    }

    private function normalizePayloadValue(mixed $value): mixed
    {
        if ($value === null || is_int($value) || is_float($value) || is_string($value) || is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            return $this->normalizeArray($value);
        }

        throw new OperationCodecException('Operation value property type is not supported by the JSON codec.');
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     */
    private function normalizeArray(array $value): array
    {
        foreach (array_keys($value) as $key) {
            /** @var mixed $item */
            $item = $value[$key];

            if (is_object($item) || is_resource($item)) {
                throw new OperationCodecException('Operation value arrays must not contain objects or resources.');
            }
        }

        return $value;
    }
}
