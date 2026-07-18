<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

use BlackOps\Journal\LifecycleState;

final readonly class DiagnosticsState
{
    public function __construct(
        public LifecycleState $current,
        public bool $terminal,
        public string $source,
    ) {}
}
