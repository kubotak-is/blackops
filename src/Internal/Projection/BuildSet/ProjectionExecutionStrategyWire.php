<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\BuildSet;

use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\Inline;

final readonly class ProjectionExecutionStrategyWire
{
    public function map(string $strategy): string
    {
        if ($strategy === Inline::class) {
            return 'inline';
        }
        if ($strategy === Deferred::class) {
            return 'deferred';
        }

        return 'custom:' . ltrim($strategy, characters: '\\');
    }
}
