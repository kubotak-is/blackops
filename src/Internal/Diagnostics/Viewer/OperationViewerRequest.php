<?php

declare(strict_types=1);

namespace BlackOps\Internal\Diagnostics\Viewer;

final readonly class OperationViewerRequest
{
    /** @param array<string, string> $headers */
    public function __construct(
        public string $method,
        public string $target,
        public string $protocol,
        public array $headers,
    ) {}
}
