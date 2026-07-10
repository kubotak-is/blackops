<?php

declare(strict_types=1);

namespace BlackOps\Internal\Discovery;

use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final readonly class PhpSourceFileFinder
{
    /**
     * @return list<string>
     */
    public function find(DiscoveryRoots $roots, ?string $directory = null): array
    {
        $scanRoots = $directory === null ? $roots->paths : $roots->intersections($directory);
        $files = [];

        foreach ($scanRoots as $root) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
            );

            foreach ($iterator as $file) {
                if (!$file instanceof SplFileInfo) {
                    continue;
                }

                $this->collect($file, $roots, $files);
            }
        }

        $result = array_keys($files);
        sort($result);

        return $result;
    }

    /** @param array<string, true> $files */
    private function collect(SplFileInfo $file, DiscoveryRoots $roots, array &$files): void
    {
        $resolved = $file->getRealPath();

        if ($resolved === false) {
            throw new InvalidArgumentException('Operation discovery source path could not be resolved.');
        }

        if ($file->isLink() && !$roots->contains($resolved)) {
            throw new InvalidArgumentException('Operation discovery source symlink escapes configured roots.');
        }

        if (!$file->isFile() || $file->getExtension() !== 'php') {
            return;
        }

        if (!$roots->contains($resolved)) {
            throw new InvalidArgumentException('Operation discovery source file escapes configured roots.');
        }

        if (!is_readable($resolved)) {
            throw new InvalidArgumentException('Operation discovery source file must be readable.');
        }

        $files[$resolved] = true;
    }
}
