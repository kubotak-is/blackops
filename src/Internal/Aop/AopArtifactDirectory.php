<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop;

use FilesystemIterator;
use InvalidArgumentException;
use RuntimeException;

final readonly class AopArtifactDirectory
{
    public function prepare(string $containerPath): string
    {
        $directory = $this->path($containerPath);

        if (is_link($directory)) {
            throw new InvalidArgumentException('AOP artifact directory must not be a symbolic link.');
        }

        if (file_exists($directory) && !is_dir($directory)) {
            throw new InvalidArgumentException('AOP artifact path must be a directory.');
        }

        if (
            !is_dir($directory)
            && !mkdir(directory: $directory, permissions: 0o755, recursive: true)
            && !is_dir($directory)
        ) {
            throw new RuntimeException('AOP artifact directory could not be created.');
        }

        $this->clearDirectory($directory);

        return $directory;
    }

    public function clear(string $containerPath): void
    {
        $directory = $this->path($containerPath);

        if (!is_dir($directory) || is_link($directory)) {
            return;
        }

        $this->clearDirectory($directory);
    }

    public function path(string $containerPath): string
    {
        return dirname($containerPath) . DIRECTORY_SEPARATOR . 'aop';
    }

    private function clearDirectory(string $directory): void
    {
        foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $entry) {
            assert($entry instanceof \SplFileInfo, description: 'AOP artifact iteration must yield file information.');

            if ($entry->isDir() && !$entry->isLink()) {
                throw new RuntimeException('AOP artifact directory contains an unexpected nested directory.');
            }

            if (!unlink($entry->getPathname())) {
                throw new RuntimeException('AOP artifact could not be removed.');
            }
        }
    }
}
