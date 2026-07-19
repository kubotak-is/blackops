<?php

declare(strict_types=1);

namespace BlackOps\Internal\Frontend;

final readonly class FrontendContractManifestArtifact
{
    public function __construct(
        public int $schemaVersion,
        public string $applicationBuildId,
        public FrontendContractManifest $manifest,
    ) {}
}
