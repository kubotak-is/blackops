<?php

declare(strict_types=1);

namespace BlackOps\Internal\Runtime;

use BlackOps\Internal\Aop\FrameworkProxyArtifact\FrameworkProxyArtifactManifest;

/** Loads one immutable, build-id-bound Framework proxy artifact unit. */
final readonly class FrameworkProxyProfileLoader
{
    public function __construct(
        private FrameworkProxyArtifactLoader $artifacts = new FrameworkProxyArtifactLoader(),
    ) {}

    public function load(
        string $directory,
        string $applicationBuildId,
        string $manifestHash,
    ): FrameworkProxyArtifactManifest {
        return $this->artifacts->load($directory, $applicationBuildId, $manifestHash);
    }

    public function loadFramework(
        string $directory,
        string $applicationBuildId,
        string $manifestHash,
    ): FrameworkProxyArtifactManifest {
        return $this->load($directory, $applicationBuildId, $manifestHash);
    }
}
