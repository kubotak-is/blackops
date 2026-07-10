<?php

declare(strict_types=1);

namespace BlackOps\Internal\Discovery;

use InvalidArgumentException;

final readonly class ComposerClassmapMetadata
{
    /** @var array<class-string, string> */
    private array $classes;

    public function __construct(mixed $metadata, ComposerMetadataPathResolver $paths)
    {
        if (!is_array($metadata)) {
            throw new InvalidArgumentException('Composer classmap metadata must be an array.');
        }

        $classes = [];

        foreach (array_keys($metadata) as $class) {
            if (
                !is_string($class)
                || preg_match('/^(?:[A-Za-z_][A-Za-z0-9_]*\\\\)*[A-Za-z_][A-Za-z0-9_]*$/', $class) !== 1
                || !is_string($metadata[$class])
                || $metadata[$class] === ''
            ) {
                throw new InvalidArgumentException('Composer classmap metadata entry is invalid.');
            }

            $classes[$class] = $paths->file($metadata[$class]);
        }

        $this->classes = $classes;
    }

    /**
     * @return array<class-string, string>
     */
    public function candidates(DiscoveryRoots $roots): array
    {
        $candidates = [];

        foreach ($this->classes as $class => $file) {
            if (!$roots->contains($file)) {
                continue;
            }

            $candidates[$class] = $file;
        }

        return $candidates;
    }
}
