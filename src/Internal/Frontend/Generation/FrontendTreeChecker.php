<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend\Generation;

use Throwable;

/**
 * Filesystem traversal keeps every unsafe or unstable entry outside the fresh and drift states.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class FrontendTreeChecker
{
    private const FILE_TYPE_MASK = 0o170_000;
    private const FILE_TYPE_DIRECTORY = 0o040_000;
    private const FILE_TYPE_REGULAR = 0o100_000;
    private const FILE_TYPE_SYMLINK = 0o120_000;

    public function __construct(
        private FrontendTreeCheckFilesystem $filesystem = new NativeFrontendTreeCheckFilesystem(),
    ) {}

    public function check(FrontendGeneratedTree $expected, string $output): FrontendTreeCheckState
    {
        if (!$this->invokeExists($output)) {
            return FrontendTreeCheckState::Missing;
        }

        /** @var array<string, string> $actual */
        $actual = [];
        /** @var array<string, true> $visited */
        $visited = [];
        $hasSymlink = false;
        $this->inspectDirectory($output, '', $actual, $visited, $hasSymlink);

        if ($hasSymlink) {
            return FrontendTreeCheckState::Drift;
        }

        $expectedPaths = array_keys($expected->files);
        $actualPaths = array_keys($actual);
        sort($expectedPaths, SORT_STRING);
        sort($actualPaths, SORT_STRING);
        if ($expectedPaths !== $actualPaths) {
            return FrontendTreeCheckState::Drift;
        }

        foreach ($expected->files as $path => $contents) {
            if ($actual[$path] !== $contents) {
                return FrontendTreeCheckState::Drift;
            }
        }

        return FrontendTreeCheckState::Fresh;
    }

    /**
     * @param array<string, string> $files
     * @param array<string, true> $visited
     */
    private function inspectDirectory(
        string $root,
        string $relative,
        array &$files,
        array &$visited,
        bool &$hasSymlink,
    ): void {
        $directory = $relative === '' ? $root : $root . '/' . $relative;
        $status = $this->invokeStatus($directory);
        if ($this->fileType($status) !== self::FILE_TYPE_DIRECTORY) {
            throw new FrontendTreeCheckInspectionException('Generated frontend directory is unsafe to inspect.');
        }

        $identity = sprintf('%d:%d', $status['dev'] ?? -1, $status['ino'] ?? -1);
        if (array_key_exists($identity, $visited)) {
            throw new FrontendTreeCheckInspectionException('Generated frontend directory contains a cycle.');
        }
        $visited[$identity] = true;

        $entries = $this->invokeList($directory);
        $afterList = $this->invokeStatus($directory);
        if (!$this->sameDirectory($status, $afterList)) {
            throw new FrontendTreeCheckInspectionException(
                'Generated frontend directory changed while it was inspected.',
            );
        }
        sort($entries, SORT_STRING);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $childRelative = $relative === '' ? $entry : $relative . '/' . $entry;
            $child = $root . '/' . $childRelative;
            $childStatus = $this->invokeStatus($child);
            $type = $this->fileType($childStatus);

            if ($type === self::FILE_TYPE_DIRECTORY) {
                $this->inspectDirectory($root, $childRelative, $files, $visited, $hasSymlink);

                continue;
            }
            if ($type === self::FILE_TYPE_REGULAR) {
                $contents = $this->invokeRead($child);
                $afterRead = $this->invokeStatus($child);
                if (!$this->sameFile($childStatus, $afterRead)) {
                    throw new FrontendTreeCheckInspectionException(
                        'Generated frontend file changed while it was inspected.',
                    );
                }
                $files[$childRelative] = $contents;

                continue;
            }
            if ($type === self::FILE_TYPE_SYMLINK) {
                $hasSymlink = true;

                continue;
            }

            throw new FrontendTreeCheckInspectionException('Generated frontend tree contains an unsafe entry.');
        }
    }

    /** @param array<array-key, int> $status */
    private function fileType(array $status): int
    {
        return ($status['mode'] ?? 0) & self::FILE_TYPE_MASK;
    }

    /**
     * @param array<array-key, int> $before
     * @param array<array-key, int> $after
     */
    private function sameFile(array $before, array $after): bool
    {
        foreach (['dev', 'ino', 'mode', 'size', 'mtime', 'ctime'] as $key) {
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                return false;
            }
        }

        return $this->fileType($after) === self::FILE_TYPE_REGULAR;
    }

    /**
     * @param array<array-key, int> $before
     * @param array<array-key, int> $after
     */
    private function sameDirectory(array $before, array $after): bool
    {
        foreach (['dev', 'ino', 'mode', 'mtime', 'ctime'] as $key) {
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                return false;
            }
        }

        return $this->fileType($after) === self::FILE_TYPE_DIRECTORY;
    }

    private function invokeExists(string $path): bool
    {
        try {
            return $this->filesystem->exists($path);
        } catch (Throwable) {
            throw new FrontendTreeCheckInspectionException('Generated frontend tree could not be inspected.');
        }
    }

    /** @return array<array-key, int> */
    private function invokeStatus(string $path): array
    {
        try {
            $status = $this->filesystem->status($path);
        } catch (Throwable) {
            throw new FrontendTreeCheckInspectionException('Generated frontend tree could not be inspected.');
        }
        if ($status === false) {
            throw new FrontendTreeCheckInspectionException('Generated frontend tree could not be inspected.');
        }

        return $status;
    }

    /** @return array<int, string> */
    private function invokeList(string $path): array
    {
        try {
            $entries = $this->filesystem->list($path);
        } catch (Throwable) {
            throw new FrontendTreeCheckInspectionException('Generated frontend tree could not be inspected.');
        }
        if ($entries === false) {
            throw new FrontendTreeCheckInspectionException('Generated frontend tree could not be inspected.');
        }

        return $entries;
    }

    private function invokeRead(string $path): string
    {
        try {
            $contents = $this->filesystem->read($path);
        } catch (Throwable) {
            throw new FrontendTreeCheckInspectionException('Generated frontend tree could not be inspected.');
        }
        if ($contents === false) {
            throw new FrontendTreeCheckInspectionException('Generated frontend tree could not be inspected.');
        }

        return $contents;
    }
}
