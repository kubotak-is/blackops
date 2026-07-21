<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

final readonly class ApplicationCommandManifestArtifact
{
    /**
     * @param list<ApplicationCommandMetadata> $commands
     * @param list<OperationConsoleCommandMetadata> $operationCommands
     */
    public function __construct(
        public int $schemaVersion,
        public string $applicationBuildId,
        public array $commands,
        public array $operationCommands,
    ) {}
}
