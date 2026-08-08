<?php

declare(strict_types=1);

namespace BlackOps\Telemetry;

use BlackOps\Core\Attribute\PublicApi;
use InvalidArgumentException;

/** @mago-expect lint:cyclomatic-complexity */
#[PublicApi]
final readonly class TelemetryContext
{
    private string $traceId;
    private string $spanId;
    private int $flags;
    private ?string $state;

    public function __construct(string $traceparent, ?string $tracestate = null)
    {
        $parts = explode('-', $traceparent);
        if (
            count($parts) !== 4
            || $parts[0] !== '00'
            || !$this->validIdentifier($parts[1], 32)
            || !$this->validIdentifier($parts[2], 16)
            || !preg_match('/\A[0-9a-f]{2}\z/', $parts[3])
        ) {
            throw new InvalidArgumentException('Telemetry traceparent is invalid.');
        }

        $this->traceId = $parts[1];
        $this->spanId = $parts[2];
        $this->flags = (int) hexdec($parts[3]);
        $this->state = $this->validateState($tracestate);
    }

    public function traceparent(): string
    {
        return sprintf('00-%s-%s-%02x', $this->traceId, $this->spanId, $this->flags);
    }

    public function tracestate(): ?string
    {
        return $this->state;
    }

    private function validateState(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (strlen($value) > 512) {
            throw new InvalidArgumentException('Telemetry tracestate is oversized.');
        }
        $members = explode(',', $value);
        if (count($members) > 32) {
            throw new InvalidArgumentException('Telemetry tracestate has too many members.');
        }
        $seen = [];
        $canonical = [];
        foreach ($members as $member) {
            $member = trim($member, characters: " \t");
            if (
                !preg_match(
                    '/\A([a-z][a-z0-9_*\/-]{0,255}|[a-z0-9][a-z0-9_*\/-]{0,240}@[a-z][a-z0-9_*\/-]{0,13})=([ -~]{0,255}[!-~])\z/',
                    $member,
                    $match,
                )
                || str_contains($match[2], ',')
                || str_contains($match[2], '=')
            ) {
                throw new InvalidArgumentException('Telemetry tracestate is invalid.');
            }
            if (($seen[$match[1]] ?? null) !== null) {
                throw new InvalidArgumentException('Telemetry tracestate contains a duplicate member.');
            }
            $seen[$match[1]] = true;
            $canonical[] = $match[1] . '=' . $match[2];
        }
        return implode(',', $canonical);
    }

    private function validIdentifier(string $value, int $length): bool
    {
        return preg_match('/\A[0-9a-f]{' . $length . '}\z/', $value) === 1 && preg_match('/[1-9a-f]/', $value) === 1;
    }
}
