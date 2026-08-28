<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetCodec;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetFilesystem;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetFilesystemException;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetLocator;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetReader;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetRecoveryResult;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetRecoveryStatus;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetStatusSource;
use BlackOps\Internal\Projection\BuildSet\ProjectionBuildSetValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Resolves the configured production root and adapts its complete-set status.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:no-error-control-operator
 */
final readonly class ApplicationProjectionBuildSetStatusSource implements ProjectionBuildSetStatusSource
{
    public function __construct(
        private ApplicationConfigurationSnapshot $configuration,
        private ?ProjectionBuildSetFilesystem $filesystem = null,
    ) {}

    /**
     * @template T
     * @param callable(ProjectionBuildSetRecoveryResult): T $callback
     * @return T
     */
    public function withStatus(callable $callback, string $selectedKey = 'command'): mixed
    {
        $this->validateKey($selectedKey);
        $configuration = $this->configuration->configuration();
        if (!array_key_exists('app', $configuration)) {
            return $callback($this->absent());
        }
        if (!is_array($configuration['app'])) {
            throw new ProjectionBuildSetValidationException('Application build configuration is invalid.');
        }
        if (!array_key_exists('build', $configuration['app'])) {
            return $callback($this->absent());
        }

        try {
            $build = ApplicationBuildConfiguration::fromConfiguration($configuration);
            $buildId = ApplicationBuildId::fromConfiguration($configuration);
        } catch (InvalidArgumentException $exception) {
            throw new ProjectionBuildSetValidationException(
                'Application build configuration is invalid.',
                previous: $exception,
            );
        }

        if (!$this->directoryExists($build->projectionBuildSetRoot)) {
            return $callback($this->absent());
        }

        try {
            $locator = new ProjectionBuildSetLocator($build->projectionBuildSetRoot);
        } catch (ProjectionBuildSetValidationException $exception) {
            throw new ProjectionBuildSetValidationException(
                'Application projection build-set locator is invalid.',
                previous: $exception,
            );
        }

        return new ProjectionBuildSetReader(
            $locator,
            filesystem: $this->filesystem ?? new ProjectionBuildSetFilesystem(),
        )->withPublishedStatus($buildId, $callback, $selectedKey);
    }

    private function directoryExists(string $path): bool
    {
        set_error_handler(static fn(int $severity, string $message): bool => true);
        try {
            $stat = lstat($path);
        } catch (Throwable $exception) {
            throw new ProjectionBuildSetValidationException(
                'Application projection build-set locator could not be inspected.',
                previous: $exception,
            );
        } finally {
            restore_error_handler();
        }
        if ($stat === false) {
            if ($this->missingPathHasAccessibleParent($path)) {
                return false;
            }
            throw new ProjectionBuildSetValidationException(
                'Application projection build-set locator could not be inspected.',
            );
        }
        if ((($stat['mode'] ?? 0) & 0o170_000) !== 0o040_000 || is_link($path)) {
            throw new ProjectionBuildSetValidationException(
                'Application projection build-set locator is not a directory.',
            );
        }
        $realPath = realpath($path);
        if ($realPath === false || $realPath === '' || !is_dir($realPath) || is_link($realPath)) {
            throw new ProjectionBuildSetValidationException(
                'Application projection build-set locator could not be resolved.',
            );
        }
        if (!is_readable($realPath) || !is_executable($realPath)) {
            throw new ProjectionBuildSetFilesystemException(
                'Application projection build-set locator could not be read.',
            );
        }

        return true;
    }

    private function missingPathHasAccessibleParent(string $path): bool
    {
        $parent = dirname($path);
        while ($parent !== $path) {
            $parentStat = @lstat($parent);
            if ($parentStat !== false) {
                if ((($parentStat['mode'] ?? 0) & 0o170_000) !== 0o040_000 || is_link($parent)) {
                    return false;
                }
                if (!is_readable($parent) || !is_executable($parent)) {
                    throw new ProjectionBuildSetFilesystemException(
                        'Application projection build-set locator parent could not be read.',
                    );
                }

                return true;
            }
            $next = dirname($parent);
            if ($next === $parent) {
                return false;
            }
            $parent = $next;
        }

        return false;
    }

    private function validateKey(string $key): void
    {
        if (!array_key_exists($key, new ProjectionBuildSetCodec()->kinds())) {
            throw new ProjectionBuildSetValidationException('Projection key is unsupported.');
        }
    }

    private function absent(): ProjectionBuildSetRecoveryResult
    {
        return new ProjectionBuildSetRecoveryResult(ProjectionBuildSetRecoveryStatus::ABSENT);
    }
}
