<?php

declare(strict_types=1);

namespace BlackOps\Internal\Build;

use InvalidArgumentException;

final readonly class BuildFingerprint
{
    /**
     * @param iterable<string> $paths
     */
    public function hash(iterable $paths): string
    {
        $entries = [];

        foreach ($paths as $path) {
            $entries[] = $this->entry($path);
        }

        sort($entries);

        return hash('sha256', implode("\n", $entries));
    }

    private function entry(string $path): string
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException('Build fingerprint input file does not exist.');
        }

        $modifiedAt = filemtime($path);
        $size = filesize($path);
        $realPath = realpath($path);

        if ($modifiedAt === false || $size === false || $realPath === false) {
            throw new InvalidArgumentException('Build fingerprint input file metadata could not be read.');
        }

        return $realPath . "\0" . $modifiedAt . "\0" . $size;
    }
}
