<?php

declare(strict_types=1);

namespace BlackOps\Internal\Build;

use InvalidArgumentException;
use RuntimeException;

final readonly class BuildLock
{
    public function run(string $path, callable $operation): void
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            throw new InvalidArgumentException('Build lock directory does not exist.');
        }

        $handle = fopen($path, mode: 'c');

        if ($handle === false) {
            throw new RuntimeException('Build lock file could not be opened.');
        }

        try {
            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Build lock is already held.');
            }

            try {
                $operation();
            } finally {
                flock($handle, LOCK_UN);
            }
        } finally {
            fclose($handle);
        }
    }
}
