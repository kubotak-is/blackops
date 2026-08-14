<?php

declare(strict_types=1);

namespace BlackOps\Internal\Http;

use BlackOps\Telemetry\TelemetryContext;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class TelemetryContextExtractor
{
    public function extract(ServerRequestInterface $request): ?TelemetryContext
    {
        $parents = $request->getHeader('traceparent');
        if (count($parents) !== 1 || trim($parents[0]) !== $parents[0]) {
            return null;
        }
        $states = $request->getHeader('tracestate');
        $state = count($states) > 1 ? null : $states[0] ?? null;
        try {
            return new TelemetryContext($parents[0], $state);
        } catch (Throwable) {
            return null;
        }
    }
}
