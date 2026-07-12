<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use InvalidArgumentException;

final readonly class ApplicationBasePath
{
    public function normalize(string $basePath): string
    {
        if (trim($basePath) === '') {
            throw new InvalidArgumentException('Application base path must not be empty.');
        }

        $resolved = realpath($basePath);

        if ($resolved === false || !is_dir($resolved)) {
            throw new InvalidArgumentException('Application base path must be an existing directory.');
        }

        return $resolved === DIRECTORY_SEPARATOR ? $resolved : rtrim($resolved, DIRECTORY_SEPARATOR);
    }
}
