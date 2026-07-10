<?php

declare(strict_types=1);

namespace BlackOps\Internal\Discovery;

use InvalidArgumentException;

final readonly class ComposerAutoloadMetadata
{
    private ComposerPsr4Metadata $psr4;

    private ComposerClassmapMetadata $classmap;

    public function __construct(string $baseDirectory, mixed $psr4, mixed $classmap)
    {
        $paths = new ComposerMetadataPathResolver($baseDirectory);
        $this->psr4 = new ComposerPsr4Metadata($psr4, $paths);
        $this->classmap = new ComposerClassmapMetadata($classmap, $paths);
    }

    /**
     * @return array<class-string, string>
     */
    public function candidates(DiscoveryRoots $roots): array
    {
        $candidates = $this->psr4->candidates($roots);

        foreach ($this->classmap->candidates($roots) as $class => $file) {
            if (array_key_exists($class, $candidates) && $candidates[$class] !== $file) {
                throw new InvalidArgumentException('Composer autoload metadata maps a class to multiple files.');
            }

            $candidates[$class] = $file;
        }

        ksort($candidates);

        return $candidates;
    }
}
