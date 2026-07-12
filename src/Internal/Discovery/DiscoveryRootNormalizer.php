<?php

declare(strict_types=1);

namespace BlackOps\Internal\Discovery;

use InvalidArgumentException;

final readonly class DiscoveryRootNormalizer
{
    /**
     * @param iterable<string> $roots
     *
     * @return non-empty-list<string>
     */
    public function normalize(iterable $roots): array
    {
        $paths = [];

        foreach ($roots as $root) {
            if ($root === '') {
                throw new InvalidArgumentException('Operation discovery root must be a non-empty directory path.');
            }

            if (!$this->isAbsolute($root)) {
                throw new InvalidArgumentException('Operation discovery root must be an absolute directory path.');
            }

            $resolved = realpath($root);

            if ($resolved === false || !is_dir($resolved) || !is_readable($resolved)) {
                throw new InvalidArgumentException('Operation discovery root must be a readable directory.');
            }

            $paths[$resolved] = true;
        }

        if ($paths === []) {
            throw new InvalidArgumentException('Operation discovery requires at least one root.');
        }

        $result = array_keys($paths);
        sort($result);

        return $result;
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
