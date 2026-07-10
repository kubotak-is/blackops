<?php

declare(strict_types=1);

namespace BlackOps\Transport\PostgreSql;

use JsonException;
use RuntimeException;

final readonly class PostgreSqlJson
{
    /**
     * @param array<array-key, mixed> $value
     */
    public function encode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function decode(string $payload): array
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($payload, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Journal record payload is not valid JSON.', previous: $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Journal record payload must be an object.');
        }

        return $decoded;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    public function array(array $data, string $key): array
    {
        if (!array_key_exists($key, $data) || !is_array($data[$key])) {
            throw new RuntimeException("Stored journal field '{$key}' must be an array.");
        }

        return $data[$key];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function string(array $data, string $key): string
    {
        if (!array_key_exists($key, $data) || !is_string($data[$key])) {
            throw new RuntimeException("Stored journal field '{$key}' must be a string.");
        }

        return $data[$key];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function int(array $data, string $key): int
    {
        if (!array_key_exists($key, $data) || !is_int($data[$key])) {
            throw new RuntimeException("Stored journal field '{$key}' must be an integer.");
        }

        return $data[$key];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function bool(array $data, string $key): bool
    {
        if (!array_key_exists($key, $data) || !is_bool($data[$key])) {
            throw new RuntimeException("Stored journal field '{$key}' must be a boolean.");
        }

        return $data[$key];
    }
}
