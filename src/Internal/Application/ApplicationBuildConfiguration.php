<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use InvalidArgumentException;

final readonly class ApplicationBuildConfiguration
{
    private function __construct(
        public string $operationManifest,
        public string $httpManifest,
        public string $container,
        public string $containerClass,
        public string $containerNamespace,
    ) {}

    /** @param array<string, array<array-key, mixed>> $configuration */
    public static function fromConfiguration(array $configuration): self
    {
        /** @var mixed $build */
        $build = $configuration['app']['build'] ?? null;

        if (!is_array($build)) {
            throw new InvalidArgumentException('Application configuration key "app.build" must be an array.');
        }

        $operationManifest = self::absolutePath($build, 'operation_manifest');
        $httpManifest = self::absolutePath($build, 'http_manifest');
        $container = self::absolutePath($build, 'container');
        $containerClass = self::identifier($build, 'container_class');
        $containerNamespace = self::namespace($build);

        return new self($operationManifest, $httpManifest, $container, $containerClass, $containerNamespace);
    }

    /** @param array<array-key, mixed> $build */
    private static function absolutePath(array $build, string $key): string
    {
        /** @var mixed $path */
        $path = $build[$key] ?? null;

        if (!is_string($path) || $path === '' || !str_starts_with($path, DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException(sprintf(
                'Application configuration key "app.build.%s" must be a non-empty absolute path.',
                $key,
            ));
        }

        return $path;
    }

    /** @param array<array-key, mixed> $build */
    private static function identifier(array $build, string $key): string
    {
        /** @var mixed $value */
        $value = $build[$key] ?? null;

        if (!is_string($value) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Application configuration key "app.build.%s" must be a non-empty class identifier.',
                $key,
            ));
        }

        return $value;
    }

    /** @param array<array-key, mixed> $build */
    private static function namespace(array $build): string
    {
        /** @var mixed $namespace */
        $namespace = $build['container_namespace'] ?? null;

        if (!is_string($namespace)) {
            throw new InvalidArgumentException(
                'Application configuration key "app.build.container_namespace" must be a string.',
            );
        }

        foreach ($namespace === '' ? [] : explode('\\', $namespace) as $part) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part) !== 1) {
                throw new InvalidArgumentException(
                    'Application configuration key "app.build.container_namespace" is invalid.',
                );
            }
        }

        return $namespace;
    }
}
