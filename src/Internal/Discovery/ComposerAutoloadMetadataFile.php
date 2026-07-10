<?php

declare(strict_types=1);

namespace BlackOps\Internal\Discovery;

use InvalidArgumentException;

final readonly class ComposerAutoloadMetadataFile
{
    public function load(string $baseDirectory, string $psr4Path, string $classmapPath): ComposerAutoloadMetadata
    {
        return new ComposerAutoloadMetadata(
            $baseDirectory,
            $this->arrayFile($psr4Path, 'Composer PSR-4 metadata'),
            $this->arrayFile($classmapPath, 'Composer classmap metadata'),
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    private function arrayFile(string $path, string $description): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException($description . ' file must be readable.');
        }

        return $this->arrayValue(
            (static fn(string $metadataPath): mixed => require $metadataPath)($path),
            $description,
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    private function arrayValue(mixed $metadata, string $description): array
    {
        if (!is_array($metadata)) {
            throw new InvalidArgumentException($description . ' file must return an array.');
        }

        return $metadata;
    }
}
