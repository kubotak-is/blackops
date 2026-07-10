<?php

declare(strict_types=1);

namespace BlackOps\Internal\Codec;

use BlackOps\Core\Codec\OperationCodecException;

final readonly class JsonObjectReader
{
    /**
     * @param array<string, mixed> $data
     */
    public function string(array $data, string $key): string
    {
        if (!array_key_exists($key, $data)) {
            throw new OperationCodecException('Encoded context is missing a required string field.');
        }

        /** @var mixed $value */
        $value = $data[$key];

        if (!is_string($value) || $value === '') {
            throw new OperationCodecException('Encoded context is missing a required string field.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function int(array $data, string $key): int
    {
        if (!array_key_exists($key, $data)) {
            throw new OperationCodecException('Encoded context is missing a required integer field.');
        }

        /** @var mixed $value */
        $value = $data[$key];

        if (!is_int($value)) {
            throw new OperationCodecException('Encoded context is missing a required integer field.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>|null
     */
    public function optionalObject(array $data, string $key): ?array
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        /** @var mixed $value */
        $value = $data[$key];

        if (!is_array($value)) {
            throw new OperationCodecException('Encoded context field must be an object or null.');
        }

        return $this->object($value);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function optionalString(array $data, string $key): ?string
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        /** @var mixed $value */
        $value = $data[$key];

        if (!is_string($value) || $value === '') {
            throw new OperationCodecException('Encoded context optional value must be a string or null.');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return array<string, mixed>
     */
    private function object(array $value): array
    {
        $object = [];

        foreach (array_keys($value) as $field) {
            if (!is_string($field)) {
                throw new OperationCodecException('Encoded context object must use string keys.');
            }

            /** @var mixed $fieldValue */
            $fieldValue = $value[$field];
            $object[$field] = $fieldValue;
        }

        return $object;
    }
}
