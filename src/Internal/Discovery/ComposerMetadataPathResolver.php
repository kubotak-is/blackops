<?php

declare(strict_types=1);

namespace BlackOps\Internal\Discovery;

use InvalidArgumentException;

final readonly class ComposerMetadataPathResolver
{
    private string $baseDirectory;

    public function __construct(string $baseDirectory)
    {
        $resolved = realpath($baseDirectory);

        if ($resolved === false || !is_dir($resolved)) {
            throw new InvalidArgumentException('Composer metadata base directory must exist.');
        }

        $this->baseDirectory = $resolved;
    }

    public function directory(string $path): string
    {
        $resolved = realpath($this->absolute($path));

        if ($resolved === false || !is_dir($resolved) || !is_readable($resolved)) {
            throw new InvalidArgumentException('Composer PSR-4 directory must be readable.');
        }

        return $resolved;
    }

    public function file(string $path): string
    {
        $resolved = realpath($this->absolute($path));

        if ($resolved === false || !is_file($resolved) || !is_readable($resolved)) {
            throw new InvalidArgumentException('Composer classmap file must be readable.');
        }

        return $resolved;
    }

    private function absolute(string $path): string
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : $this->baseDirectory . DIRECTORY_SEPARATOR . $path;
    }
}
