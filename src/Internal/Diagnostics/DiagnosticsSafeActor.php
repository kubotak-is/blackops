<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

final readonly class DiagnosticsSafeActor
{
    public string $id;

    public function __construct(
        public string $type,
    ) {
        $this->id = '[masked]';
    }
}
