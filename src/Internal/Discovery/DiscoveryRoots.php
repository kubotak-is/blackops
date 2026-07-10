<?php

declare(strict_types=1);

namespace BlackOps\Internal\Discovery;

use InvalidArgumentException;

final readonly class DiscoveryRoots
{
    /** @param non-empty-list<string> $paths */
    private function __construct(
        public array $paths,
    ) {}

    /**
     * @param iterable<string> $roots
     */
    public static function from(iterable $roots): self
    {
        return new self(new DiscoveryRootNormalizer()->normalize($roots));
    }

    public function contains(string $path): bool
    {
        foreach ($this->paths as $root) {
            if ($this->isWithin($path, $root)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function intersections(string $directory): array
    {
        $resolved = realpath($directory);

        if ($resolved === false || !is_dir($resolved) || !is_readable($resolved)) {
            throw new InvalidArgumentException('Composer PSR-4 directory must be readable.');
        }

        $intersections = [];

        foreach ($this->paths as $root) {
            if ($this->isWithin($resolved, $root)) {
                $intersections[$resolved] = true;
                continue;
            }

            if ($this->isWithin($root, $resolved)) {
                $intersections[$root] = true;
            }
        }

        return array_keys($intersections);
    }

    private function isWithin(string $path, string $root): bool
    {
        if ($root === DIRECTORY_SEPARATOR) {
            return str_starts_with($path, DIRECTORY_SEPARATOR);
        }

        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }
}
