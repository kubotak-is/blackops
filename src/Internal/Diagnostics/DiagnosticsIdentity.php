<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics;

final readonly class DiagnosticsIdentity
{
    public function __construct(
        public string $operationId,
        public string $type,
        public int $schemaVersion,
        public string $strategy,
        public ?string $correlationId,
        public ?string $causationId,
        public ?DiagnosticsSafeActorContext $actors,
    ) {}
}
