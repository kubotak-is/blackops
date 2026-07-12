<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use InvalidArgumentException;

final readonly class ApplicationEnvironment
{
    /**
     * @param array<array-key, mixed> $variables
     * @return array<string, string>
     */
    public function validate(array $variables): array
    {
        $environment = [];
        array_walk($variables, static function (mixed $value, int|string $name) use (&$environment): void {
            if (!is_string($name) || $name === '') {
                throw new InvalidArgumentException('Environment variable names must be non-empty strings.');
            }

            if (!is_string($value)) {
                throw new InvalidArgumentException(sprintf(
                    'Environment variable "%s" must have a string value.',
                    $name,
                ));
            }

            $environment[$name] = $value;
        });

        return $environment;
    }
}
