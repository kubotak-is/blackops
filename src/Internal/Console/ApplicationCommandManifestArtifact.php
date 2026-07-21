<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

final readonly class ApplicationCommandManifestArtifact
{
    /** @param list<ApplicationCommandMetadata> $commands */
    public function __construct(
        public int $schemaVersion,
        public string $applicationBuildId,
        public array $commands,
    ) {}
}
