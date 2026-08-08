<?php

declare(strict_types=1);

namespace BlackOps\Internal\Telemetry;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ScopeInterface;
use Throwable;

/** @mago-expect lint:no-empty-catch-clause */
final class TelemetrySpanScope
{
    /** @var list<string> */
    private const RESULTS = [
        'completed',
        'rejected',
        'failed',
        'retry_scheduled',
        'dead_lettered',
        'interrupted',
    ];

    private bool $ended = false;

    public function __construct(
        private readonly ?SpanInterface $span,
        private readonly ?ScopeInterface $scope,
    ) {}

    public function fail(?Throwable $failure = null): void
    {
        $span = $this->span;
        if ($span === null || $this->ended) {
            return;
        }
        try {
            $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR);
            $this->result('failed');
            if ($failure !== null) {
                $span->setAttribute('error.type', $this->safeErrorType($failure));
            }
        } catch (Throwable) {
        }
    }

    public function result(string $result): void
    {
        if ($this->span === null || $this->ended || !in_array($result, self::RESULTS, strict: true)) {
            return;
        }
        try {
            $this->span->setAttribute('blackops.result', $result);
        } catch (Throwable) {
        }
    }

    public function end(): void
    {
        if ($this->ended) {
            return;
        }
        $this->ended = true;
        try {
            $this->scope?->detach();
        } catch (Throwable) {
        }
        try {
            $this->span?->end();
        } catch (Throwable) {
        }
    }

    private function safeErrorType(Throwable $failure): string
    {
        $class = $failure::class;
        $pos = strrpos(haystack: $class, needle: '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }
}
