<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use Dotenv\Dotenv;
use InvalidArgumentException;
use Throwable;

final readonly class ApplicationEnvironmentFile
{
    /**
     * @param array<string, string> $process
     * @return array<string, string>
     */
    public function load(string $path, array $process): array
    {
        if ($path === '') {
            throw new InvalidArgumentException('Application environment file path must not be empty.');
        }

        if (!file_exists($path)) {
            return $process;
        }

        if (!is_file($path)) {
            throw new InvalidArgumentException('Application environment file must be a regular file.');
        }

        if (!is_readable($path)) {
            throw new InvalidArgumentException('Application environment file could not be read safely.');
        }

        try {
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new InvalidArgumentException('Application environment file could not be read safely.');
            }

            /** @var array<array-key, mixed> $file */
            $file = Dotenv::parse($contents);
        } catch (Throwable) {
            throw new InvalidArgumentException('Application environment file could not be loaded safely.');
        }

        $variables = [];
        array_walk($file, static function (mixed $value, int|string $name) use (&$variables): void {
            if (!is_string($name) || !is_string($value)) {
                throw new InvalidArgumentException('Application environment file contains an invalid value.');
            }

            $variables[$name] = $value;
        });

        return [...$variables, ...$process];
    }

    /** @param array<string, string> $variables */
    public function synchronize(array $variables): void
    {
        foreach ($variables as $name => $value) {
            $_ENV[$name] = $value;
        }
    }
}
