<?php

declare(strict_types=1);

namespace BlackOps\Http\Routing;

final readonly class HttpOperationManifestArtifact
{
    public function __construct(
        public int $schemaVersion,
        public string $applicationBuildId,
        public HttpOperationManifest $manifest,
    ) {}
}
