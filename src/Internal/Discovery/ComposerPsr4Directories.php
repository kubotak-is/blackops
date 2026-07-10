<?php

declare(strict_types=1);

namespace BlackOps\Internal\Discovery;

use InvalidArgumentException;

final readonly class ComposerPsr4Directories
{
    /**
     * @return non-empty-list<string>
     */
    public function resolve(mixed $directories, ComposerMetadataPathResolver $paths): array
    {
        if (is_string($directories)) {
            return [$paths->directory($this->path($directories))];
        }

        if (!is_array($directories) || !array_is_list($directories) || $directories === []) {
            throw new InvalidArgumentException('Composer PSR-4 directories must be a non-empty list.');
        }

        $resolved = [];

        foreach (array_keys($directories) as $index) {
            if (!is_string($directories[$index])) {
                throw new InvalidArgumentException('Composer PSR-4 directory must be a non-empty path.');
            }

            $resolved[] = $paths->directory($this->path($directories[$index]));
        }

        return array_values(array_unique($resolved));
    }

    private function path(string $path): string
    {
        if ($path === '') {
            throw new InvalidArgumentException('Composer PSR-4 directory must be a non-empty path.');
        }

        return $path;
    }
}
