<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Console\ApplicationCommandMetadata;
use BlackOps\Internal\Console\OperationConsoleCommandMetadata;

final readonly class ApplicationCommandRuntimeManifest
{
    /**
     * @param list<ApplicationCommandMetadata> $commands
     * @param list<OperationConsoleCommandMetadata> $operationCommands
     */
    public function __construct(
        public ApplicationBuildConfiguration $build,
        public array $commands,
        public array $operationCommands,
    ) {}
}
