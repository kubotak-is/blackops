<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use InvalidArgumentException;

final readonly class BuildArtifactFingerprintInputs
{
    /** @return list<string> */
    public function collect(
        string $operationProviders,
        string $serviceProviders,
        ?string $composerMetadata,
        ?string $installedComposerMetadata,
        ?string $extra,
    ): array {
        $paths = [$operationProviders, $serviceProviders];

        if ($composerMetadata !== null) {
            $paths[] = $composerMetadata;
        }

        if ($installedComposerMetadata !== null) {
            $paths[] = $installedComposerMetadata;
        }

        if ($extra === null) {
            return $paths;
        }

        $extraPaths = explode(PATH_SEPARATOR, $extra);

        foreach ($extraPaths as $path) {
            if ($path === '') {
                throw new InvalidArgumentException(
                    'Build command fingerprint input option must contain non-empty paths.',
                );
            }
        }

        return [...$paths, ...$extraPaths];
    }
}
