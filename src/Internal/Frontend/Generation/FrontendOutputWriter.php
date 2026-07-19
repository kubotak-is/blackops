<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend\Generation;

use Closure;
use InvalidArgumentException;
use RuntimeException;

/**
 * Filesystem replacement keeps every mutation behind validation and rollback branches.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class FrontendOutputWriter
{
    /** @var Closure(string, string): bool */
    private Closure $rename;

    /** @param (Closure(string, string): bool)|null $rename */
    public function __construct(?Closure $rename = null)
    {
        $this->rename = $rename ?? rename(...);
    }

    public function write(FrontendGeneratedTree $tree, string $output): int
    {
        if (!str_starts_with($output, '/')) {
            throw new InvalidArgumentException('Generated frontend output path is invalid.');
        }
        $this->assertNoSymlinkAncestors($output);
        $this->assertOwnedOrEmpty($output);

        $parent = dirname($output);
        $this->createParent($parent);
        $this->assertNoSymlinkAncestors($output);

        $temporary = $parent . '/.blackops-frontend-tmp-' . bin2hex(random_bytes(8));
        $backup = $parent . '/.blackops-frontend-backup-' . bin2hex(random_bytes(8));
        if (!mkdir($temporary, permissions: 0o777)) {
            throw new RuntimeException('Generated frontend temporary directory could not be created.');
        }

        $backedUp = false;
        try {
            $this->writeTree($temporary, $tree);
            $this->verifyTree($temporary, $tree);

            if (is_dir($output)) {
                if (!($this->rename)($output, $backup)) {
                    throw new RuntimeException('Existing generated frontend tree could not be backed up.');
                }
                $backedUp = true;
            }

            if (!($this->rename)($temporary, $output)) {
                if ($backedUp && !($this->rename)($backup, $output)) {
                    throw new RuntimeException(
                        'Generated frontend replacement failed and the previous tree could not be restored.',
                    );
                }
                $backedUp = false;

                throw new RuntimeException('Generated frontend tree could not be moved into place.');
            }

            if ($backedUp) {
                $this->removeTree($backup);
                $backedUp = false;
            }

            return count($tree->files);
        } finally {
            if (is_dir($temporary) || is_link($temporary)) {
                $this->removeTree($temporary);
            }
            if (is_dir($output) && is_dir($backup)) {
                $this->removeTree($backup);
            }
        }
    }

    private function assertOwnedOrEmpty(string $output): void
    {
        if (!file_exists($output)) {
            return;
        }
        if (!is_dir($output)) {
            throw new InvalidArgumentException('Generated frontend output must be a directory.');
        }

        $scanned = scandir($output);
        $entries = array_values(array_diff($scanned === false ? [] : $scanned, ['.', '..']));
        if ($entries === []) {
            return;
        }

        $marker = $output . '/manifest.json';
        if (!is_file($marker) || is_link($marker)) {
            throw new InvalidArgumentException('Generated frontend output is not owned by BlackOps.');
        }
        $contents = file_get_contents($marker);
        if ($contents === false) {
            throw new InvalidArgumentException('Generated frontend ownership marker cannot be read.');
        }
        FrontendGenerationMarker::decode($contents);
    }

    private function createParent(string $parent): void
    {
        if (is_dir($parent)) {
            return;
        }
        if (file_exists($parent) || !mkdir($parent, permissions: 0o777, recursive: true)) {
            throw new RuntimeException('Generated frontend output parent could not be created.');
        }
    }

    private function assertNoSymlinkAncestors(string $path): void
    {
        $current = '';
        foreach (explode('/', $path) as $segment) {
            if ($segment === '') {
                continue;
            }
            $current .= '/' . $segment;
            if (is_link($current)) {
                throw new InvalidArgumentException('Generated frontend output must not use symbolic links.');
            }
        }
    }

    private function writeTree(string $root, FrontendGeneratedTree $tree): void
    {
        foreach ($tree->files as $path => $contents) {
            $target = $root . '/' . $path;
            $directory = dirname($target);
            if (!is_dir($directory) && !mkdir($directory, permissions: 0o777, recursive: true)) {
                throw new RuntimeException('Generated frontend directory could not be created.');
            }
            if (file_put_contents($target, $contents) !== strlen($contents)) {
                throw new RuntimeException('Generated frontend file could not be written.');
            }
        }
    }

    private function verifyTree(string $root, FrontendGeneratedTree $tree): void
    {
        foreach ($tree->files as $path => $contents) {
            if (file_get_contents($root . '/' . $path) !== $contents) {
                throw new RuntimeException('Generated frontend file verification failed.');
            }
        }

        $marker = file_get_contents($root . '/manifest.json');
        if ($marker === false) {
            throw new RuntimeException('Generated frontend ownership marker is missing.');
        }
        FrontendGenerationMarker::decode($marker);
    }

    private function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            if (!unlink($path)) {
                throw new RuntimeException('Generated frontend temporary file could not be cleaned up.');
            }

            return;
        }

        $entries = scandir($path);
        if ($entries === false) {
            throw new RuntimeException('Generated frontend temporary directory could not be inspected.');
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $this->removeTree($path . '/' . $entry);
        }
        if (!rmdir($path)) {
            throw new RuntimeException('Generated frontend temporary directory could not be cleaned up.');
        }
    }
}
