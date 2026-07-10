<?php

declare(strict_types=1);

namespace BlackOps\Internal\Registry;

use BlackOps\Core\Registry\OperationRegistry;

final readonly class OperationManifestArtifact
{
    public function __construct(
        public int $schemaVersion,
        public string $applicationBuildId,
        public OperationRegistry $operations,
    ) {}
}
