<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

final readonly class DiagnosticsSafeActorContext
{
    public function __construct(
        public ?DiagnosticsSafeActor $origin,
        public ?DiagnosticsSafeActor $authorization,
        public DiagnosticsSafeActor $execution,
    ) {}
}
