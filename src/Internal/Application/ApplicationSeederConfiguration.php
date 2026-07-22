<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use InvalidArgumentException;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class ApplicationSeederConfiguration
{
    public const DEFAULT_ROOT = 'App\\Infrastructure\\Seed\\DatabaseSeeder';

    /**
     * @param list<string> $discoveryRoots
     * @param class-string $root
     */
    private function __construct(
        public string $root,
        public bool $explicitRoot,
        public array $discoveryRoots,
    ) {}

    public static function fromSnapshot(ApplicationConfigurationSnapshot $application): self
    {
        $basePath = self::basePath($application->basePath());
        $database = $application->configuration()['database'] ?? [];

        /** @var mixed $configured */
        $configured = $database['seeding'] ?? [];
        if (!is_array($configured)) {
            throw new InvalidArgumentException('Application configuration key "database.seeding" must be an array.');
        }

        $explicitRoot = array_key_exists('root', $configured);
        $root = $explicitRoot ? self::root($configured['root']) : self::DEFAULT_ROOT;
        $discoveryRoots = array_key_exists('discovery', $configured)
            ? self::explicitDiscoveryRoots($configured['discovery'], $basePath)
            : self::defaultDiscoveryRoots($basePath);

        return new self($root, $explicitRoot, $discoveryRoots);
    }

    private static function basePath(string $basePath): string
    {
        $resolved = realpath($basePath);
        if ($resolved === false || !is_dir($resolved)) {
            throw new InvalidArgumentException('Application base path must be an existing directory.');
        }

        return $resolved;
    }

    /** @return class-string */
    private static function root(mixed $root): string
    {
        if (!is_string($root) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $root) !== 1) {
            throw new InvalidArgumentException(
                'Application configuration key "database.seeding.root" must be a non-empty class name.',
            );
        }

        return $root;
    }

    /** @return list<string> */
    private static function explicitDiscoveryRoots(mixed $configured, string $basePath): array
    {
        if (!is_iterable($configured)) {
            throw new InvalidArgumentException(
                'Application configuration key "database.seeding.discovery" must be iterable.',
            );
        }

        $entries = is_array($configured)
            ? array_values($configured)
            : iterator_to_array($configured, preserve_keys: false);
        if ($entries === []) {
            throw new InvalidArgumentException(
                'Application configuration key "database.seeding.discovery" must not be empty.',
            );
        }

        $roots = [];
        array_walk($entries, static function (mixed $entry) use (&$roots, $basePath): void {
            if (!is_string($entry)) {
                throw new InvalidArgumentException('Seeder discovery root must be a string.');
            }

            $resolved = self::discoveryRoot($entry, $basePath);
            if (!in_array($resolved, $roots, strict: true)) {
                $roots[] = $resolved;
            }
        });

        return $roots;
    }

    /** @return list<string> */
    private static function defaultDiscoveryRoots(string $basePath): array
    {
        $path =
            $basePath
            . DIRECTORY_SEPARATOR
            . 'app'
            . DIRECTORY_SEPARATOR
            . 'Infrastructure'
            . DIRECTORY_SEPARATOR
            . 'Seed';
        if (!file_exists($path) && !is_link($path)) {
            return [];
        }

        return [self::discoveryRoot($path, $basePath)];
    }

    private static function discoveryRoot(string $path, string $basePath): string
    {
        if ($path === '' || !str_starts_with($path, DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('Seeder discovery root must be a non-empty absolute path.');
        }

        $resolved = realpath($path);
        if ($resolved === false || !is_dir($resolved) || !is_readable($resolved)) {
            throw new InvalidArgumentException('Seeder discovery root must be a readable directory.');
        }
        if (!self::isWithin($resolved, $basePath)) {
            throw new InvalidArgumentException('Seeder discovery root must remain inside the application base path.');
        }

        return $resolved;
    }

    private static function isWithin(string $path, string $basePath): bool
    {
        return $path === $basePath || str_starts_with($path, $basePath . DIRECTORY_SEPARATOR);
    }
}
