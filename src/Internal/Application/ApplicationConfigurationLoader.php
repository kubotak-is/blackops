<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use InvalidArgumentException;

final readonly class ApplicationConfigurationLoader
{
    /** @var list<string> */
    private const FILES = ['app', 'database', 'operations', 'execution', 'journal'];

    /** @return array<string, array<array-key, mixed>> */
    public function load(string $directory): array
    {
        $resolved = realpath($directory);

        if ($resolved === false || !is_dir($resolved)) {
            throw new InvalidArgumentException('Application configuration directory must be an existing directory.');
        }

        $configuration = [];

        foreach (self::FILES as $name) {
            $path = $resolved . DIRECTORY_SEPARATOR . $name . '.php';

            if (!is_file($path)) {
                continue;
            }

            $configuration[$name] = $this->arrayFromValue($this->requireFile($path), $name);
        }

        return $configuration;
    }

    /** @return array<string, array<array-key, mixed>> */
    public function loadOptional(string $directory): array
    {
        return is_dir($directory) ? $this->load($directory) : [];
    }

    private function requireFile(string $path): mixed
    {
        return (static fn(): mixed => require $path)();
    }

    /** @return array<array-key, mixed> */
    private function arrayFromValue(mixed $value, string $name): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf(
                'Application configuration file "%s.php" must return an array.',
                $name,
            ));
        }

        return $value;
    }
}
