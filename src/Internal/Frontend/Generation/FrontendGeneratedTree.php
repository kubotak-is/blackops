<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend\Generation;

use InvalidArgumentException;

final readonly class FrontendGeneratedTree
{
    /** @var array<string, string> */
    public array $files;

    /** @param array<string, string> $files */
    public function __construct(array $files)
    {
        ksort($files, SORT_STRING);
        foreach ($files as $path => $contents) {
            if (
                $path === ''
                || str_starts_with($path, '/')
                || str_contains($path, '\\')
                || str_contains($path, "\0")
                || in_array('..', explode('/', $path), strict: true)
            ) {
                throw new InvalidArgumentException('Generated frontend tree contains an invalid file.');
            }
        }

        $this->files = $files;
    }
}
