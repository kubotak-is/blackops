<?php

declare(strict_types=1);

namespace BlackOps\Internal\Database;

use InvalidArgumentException;

final readonly class DatabaseConfigurationValueValidator
{
    public function name(mixed $value, string $key): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(sprintf(
                'Application configuration key "%s" must be a non-empty connection name.',
                $key,
            ));
        }

        return trim($value);
    }

    /** @return array<string, mixed> */
    public function parameters(mixed $value, string $key): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf(
                'Application configuration key "%s" must be a parameter map.',
                $key,
            ));
        }

        foreach (array_keys($value) as $parameter) {
            if (!is_string($parameter) || trim($parameter) === '' || trim($parameter) !== $parameter) {
                throw new InvalidArgumentException(sprintf(
                    'Application configuration key "%s" must use non-empty string parameter names.',
                    $key,
                ));
            }
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    public function schema(mixed $value, string $key): string
    {
        if (!is_string($value) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Application configuration key "%s" must be a valid PostgreSQL identifier.',
                $key,
            ));
        }

        return $value;
    }
}
