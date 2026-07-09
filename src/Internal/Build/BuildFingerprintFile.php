<?php

declare(strict_types=1);

namespace BlackOps\Internal\Build;

use InvalidArgumentException;
use RuntimeException;

final readonly class BuildFingerprintFile
{
    public function matches(string $path, string $hash): bool
    {
        if (!is_file($path)) {
            return false;
        }

        return trim((string) file_get_contents($path)) === $hash;
    }

    public function write(string $path, string $hash): void
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            throw new InvalidArgumentException('Build fingerprint directory does not exist.');
        }

        if (file_put_contents($path, $hash . "\n") === false) {
            throw new RuntimeException('Build fingerprint file could not be written.');
        }
    }
}
