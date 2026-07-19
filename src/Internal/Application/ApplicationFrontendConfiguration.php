<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use InvalidArgumentException;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class ApplicationFrontendConfiguration
{
    private function __construct(
        public string $output,
        public string $relativeOutput,
    ) {}

    /** @param array<string, array<array-key, mixed>> $configuration */
    public static function fromConfiguration(string $basePath, array $configuration): self
    {
        $root = realpath($basePath);
        if ($root === false || !is_dir($root)) {
            throw new InvalidArgumentException('Application root must be an existing directory.');
        }

        /** @var mixed $frontend */
        $frontend = $configuration['frontend'] ?? [];
        if (!is_array($frontend)) {
            throw new InvalidArgumentException('Application configuration key "frontend" must be an array.');
        }

        /** @var mixed $configuredOutput */
        $configuredOutput = $frontend['output'] ?? $root . '/resources/js/blackops';
        if (!is_string($configuredOutput) || $configuredOutput === '' || !str_starts_with($configuredOutput, '/')) {
            throw new InvalidArgumentException(
                'Application configuration key "frontend.output" must be a non-empty absolute path.',
            );
        }

        $output = self::normalizeAbsolutePath($configuredOutput);
        $prefix = $root . '/';
        if ($output === $root || !str_starts_with($output, $prefix)) {
            throw new InvalidArgumentException('Application frontend output must be inside the application root.');
        }

        self::assertNoSymlink($root, $output);

        return new self($output, substr($output, strlen($prefix)));
    }

    private static function normalizeAbsolutePath(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw new InvalidArgumentException('Application frontend output path is invalid.');
        }

        /** @var list<string> $segments */
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return '/' . implode('/', $segments);
    }

    private static function assertNoSymlink(string $root, string $output): void
    {
        $current = $root;
        $relative = substr($output, strlen($root) + 1);
        foreach (explode('/', $relative) as $segment) {
            $current .= '/' . $segment;
            if (is_link($current)) {
                throw new InvalidArgumentException('Application frontend output must not use symbolic links.');
            }
            if (file_exists($current) && !is_dir($current)) {
                throw new InvalidArgumentException('Application frontend output path must contain only directories.');
            }
        }
    }
}
