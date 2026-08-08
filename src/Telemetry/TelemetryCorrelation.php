<?php

declare(strict_types=1);

namespace BlackOps\Telemetry;

use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;

#[PublicApi]
final readonly class TelemetryCorrelation
{
    public string $traceId;
    public string $spanId;
    public bool $sampled;

    public function __construct(string $traceId, string $spanId, bool $sampled)
    {
        if (!$this->validIdentifier($traceId, 32) || !$this->validIdentifier($spanId, 16)) {
            throw new InvalidArgumentException('Telemetry correlation is invalid.');
        }
        $this->traceId = $traceId;
        $this->spanId = $spanId;
        $this->sampled = $sampled;
    }

    public static function fromContext(?TelemetryContext $context): ?self
    {
        if ($context === null) {
            return null;
        }
        $parts = explode('-', $context->traceparent());
        return new self($parts[1], $parts[2], ((int) hexdec($parts[3]) & 1) === 1);
    }

    private function validIdentifier(string $value, int $length): bool
    {
        return preg_match('/\A[0-9a-f]{' . $length . '}\z/', $value) === 1 && preg_match('/[1-9a-f]/', $value) === 1;
    }
}
