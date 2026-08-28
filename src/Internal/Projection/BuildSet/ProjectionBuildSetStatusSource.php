<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\BuildSet;

interface ProjectionBuildSetStatusSource
{
    /**
     * @template T
     * @param callable(ProjectionBuildSetRecoveryResult): T $callback
     * @return T
     */
    public function withStatus(callable $callback, string $selectedKey = 'command'): mixed;
}
