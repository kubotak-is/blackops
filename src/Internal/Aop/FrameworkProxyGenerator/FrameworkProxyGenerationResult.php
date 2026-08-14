<?php

declare(strict_types=1);

namespace BlackOps\Internal\Aop\FrameworkProxyGenerator;

use BlackOps\Internal\Aop\FrameworkProxyArtifact\FrameworkProxyArtifactManifest;

final readonly class FrameworkProxyGenerationResult
{
    /** @param array<class-string,string> $classMap */
    public function __construct(
        public string $directory,
        public FrameworkProxyArtifactManifest $manifest,
        public array $classMap,
    ) {}
}
