<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\BuildSet;

/** @mago-expect lint:no-error-control-operator */
final readonly class ProjectionBuildSetLocator
{
    public const ROOT_NAME = 'projection-build-set.json';
    public const LOCK_NAME = '.blackops-projection-build-set.lock';

    private string $directory;

    public function __construct(string $directory)
    {
        if ($directory === '' || str_contains($directory, "\0") || $directory[0] !== '/') {
            throw new ProjectionBuildSetValidationException('Projection build-set root must be an absolute directory.');
        }

        $stat = @lstat($directory);
        if ($stat === false || (($stat['mode'] ?? 0) & 0o170_000) !== 0o040_000 || is_link($directory)) {
            throw new ProjectionBuildSetValidationException(
                'Projection build-set root must be an existing non-symlink directory.',
            );
        }

        $realPath = realpath($directory);
        if ($realPath === false || $realPath === '' || !is_dir($realPath) || is_link($realPath)) {
            throw new ProjectionBuildSetValidationException(
                'Projection build-set root could not be resolved as a directory.',
            );
        }

        $this->directory = $realPath;
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public function rootPath(): string
    {
        return $this->directory . '/' . self::ROOT_NAME;
    }

    public function lockPath(): string
    {
        return $this->directory . '/' . self::LOCK_NAME;
    }

    public function path(string $basename): string
    {
        if (
            $basename === ''
            || str_contains($basename, '/')
            || str_contains($basename, "\\")
            || str_contains($basename, "\0")
        ) {
            throw new ProjectionBuildSetValidationException('Projection build-set paths must be single basenames.');
        }

        return $this->directory . '/' . $basename;
    }
}
