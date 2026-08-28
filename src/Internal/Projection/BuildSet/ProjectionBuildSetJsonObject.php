<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\BuildSet;

final readonly class ProjectionBuildSetJsonObject
{
    /** @param array<string, mixed> $fields */
    public function __construct(
        public array $fields,
    ) {}
}
