<?php

declare(strict_types=1);

namespace BlackOps\Internal\Application;

use BlackOps\Internal\Console\ApplicationCommandMetadata;

final readonly class ApplicationCommandRuntimeManifest
{
    /** @param list<ApplicationCommandMetadata> $commands */
    public function __construct(
        public ApplicationBuildConfiguration $build,
        public array $commands,
    ) {}
}
