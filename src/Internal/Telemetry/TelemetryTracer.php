<?php

declare(strict_types=1);

namespace BlackOps\Internal\Telemetry;

use BlackOps\Core\ExecutionContext;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Telemetry\TelemetryContext;
use BlackOps\Telemetry\TelemetryCorrelation;
use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanContext;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\API\Trace\TraceState;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use Throwable;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class TelemetryTracer
{
    public const SCOPE = 'blackops.framework';
    public const VERSION = '1.2.0';
    public const KIND_INTERNAL = SpanKind::KIND_INTERNAL;
    public const KIND_PRODUCER = SpanKind::KIND_PRODUCER;
    public const KIND_CONSUMER = SpanKind::KIND_CONSUMER;

    /** @var list<string> */
    private const RESULTS = [
        'completed',
        'rejected',
        'failed',
        'retry_scheduled',
        'dead_lettered',
        'interrupted',
    ];

    /** @var list<string> */
    private const RUNTIME_KINDS = [
        'operation',
        'worker',
        'scheduler',
        'maintenance',
        'outbox_relay',
        'observer_replay',
    ];

    private TracerProviderInterface $provider;

    public function __construct(?TracerProviderInterface $provider = null)
    {
        $this->provider = $provider ?? new NoopTracerProvider();
    }

    /**
     * @mago-expect lint:no-empty-catch-clause
     * @param array<string, mixed> $attributes
     */
    public function start(
        string $name,
        int $kind = SpanKind::KIND_INTERNAL,
        ?TelemetryContext $parent = null,
        array $attributes = [],
    ): TelemetrySpanScope {
        if ($name === '') {
            return new TelemetrySpanScope(null, null);
        }
        try {
            $tracer = $this->provider->getTracer(self::SCOPE, self::VERSION);
            $spanKind = match ($kind) {
                SpanKind::KIND_INTERNAL => SpanKind::KIND_INTERNAL,
                SpanKind::KIND_SERVER => SpanKind::KIND_SERVER,
                SpanKind::KIND_CLIENT => SpanKind::KIND_CLIENT,
                SpanKind::KIND_PRODUCER => SpanKind::KIND_PRODUCER,
                SpanKind::KIND_CONSUMER => SpanKind::KIND_CONSUMER,
                default => SpanKind::KIND_INTERNAL,
            };
            $builder = $tracer->spanBuilder($name)->setSpanKind($spanKind);
            $active = Span::getCurrent();
            $activeContext = $active->getContext();
            if ($activeContext->isValid()) {
                $builder = $builder->setParent(Context::getCurrent());
            }
            if (!$activeContext->isValid() && $parent !== null) {
                $builder = $builder->setParent($this->parentContext($parent));
            }
            $span = $builder->setAttributes($this->safeAttributes($attributes))->startSpan();
            try {
                $scope = $span->activate();
            } catch (Throwable) {
                try {
                    $span->end();
                } catch (Throwable) {
                }
                return new TelemetrySpanScope(null, null);
            }

            return new TelemetrySpanScope($span, $scope);
        } catch (Throwable) {
            return new TelemetrySpanScope(null, null);
        }
    }

    public function operation(
        OperationEnvelope $envelope,
        ?string $operationTypeId,
        int $kind = SpanKind::KIND_INTERNAL,
    ): TelemetrySpanScope {
        $context = $envelope->context();
        $strategy = strtolower(new \ReflectionClass($envelope->strategy())->getShortName());

        return $this->operationContext($context, $strategy, $operationTypeId, $kind);
    }

    public function operationContext(
        ExecutionContext $context,
        string $strategy,
        ?string $operationTypeId,
        int $kind = SpanKind::KIND_INTERNAL,
    ): TelemetrySpanScope {
        $attributes = [
            'blackops.operation.id' => $context->operationId()->toString(),
            'blackops.operation.type' => $operationTypeId,
            'blackops.operation.strategy' => strtolower($strategy),
            'blackops.correlation.id' => $context->correlationId()->toString(),
            'blackops.causation.id' => $context->causationId()?->toString(),
            'blackops.runtime.kind' => $kind === SpanKind::KIND_CONSUMER ? 'worker' : 'operation',
        ];
        $attempt = $context->attempt();
        if ($attempt !== null) {
            $attributes['blackops.attempt.id'] = $attempt->id()->toString();
            $attributes['blackops.attempt.number'] = $attempt->number();
        }
        $schedule = $context->schedule();
        if ($schedule !== null) {
            $attributes['blackops.schedule.name'] = $schedule->name();
        }
        $tenant = $context->tenant();
        if ($tenant !== null) {
            $attributes['blackops.tenant.type'] = $tenant->type();
            $attributes['blackops.tenant.id'] = '[masked]';
        }
        $actors = $context->actorContext();
        if ($actors !== null) {
            $origin = $actors->origin();
            if ($origin !== null) {
                $attributes['blackops.actor.origin.type'] = $origin->type();
                $attributes['blackops.actor.origin.id'] = '[masked]';
            }
            $authorization = $actors->authorization();
            if ($authorization !== null) {
                $attributes['blackops.actor.authorization.type'] = $authorization->type();
                $attributes['blackops.actor.authorization.id'] = '[masked]';
            }
            $execution = $actors->execution();
            $attributes['blackops.actor.execution.type'] = $execution->type();
            $attributes['blackops.actor.execution.id'] = '[masked]';
        }

        return $this->start(
            $kind === SpanKind::KIND_PRODUCER ? 'blackops.operation.accept' : 'blackops.operation.execute',
            $kind,
            $context->telemetry(),
            $attributes,
        );
    }

    public function currentCorrelation(): ?TelemetryCorrelation
    {
        $context = $this->currentContext();
        return $context === null ? null : TelemetryCorrelation::fromContext($context);
    }

    public function currentContext(): ?TelemetryContext
    {
        try {
            $span = Span::getCurrent();
            $context = $span->getContext();
            if (!$context->isValid()) {
                return null;
            }
            $state = $context->getTraceState();
            return new TelemetryContext(
                sprintf('00-%s-%s-%02x', $context->getTraceId(), $context->getSpanId(), $context->getTraceFlags()),
                $state === null ? null : $state->toString(),
            );
        } catch (Throwable) {
            return null;
        }
    }

    private function parentContext(TelemetryContext $parent): ContextInterface
    {
        $parts = explode('-', $parent->traceparent());
        $state = $parent->tracestate() === null ? null : new TraceState($parent->tracestate());
        $span = Span::wrap(SpanContext::createFromRemoteParent($parts[1], $parts[2], (int) hexdec($parts[3]), $state));

        return Context::getRoot()->withContextValue($span);
    }

    /** @param array<string, mixed> $attributes @return array<string, bool|int|float|string> */
    private function safeAttributes(array $attributes): array
    {
        $allowed = [
            'blackops.operation.id',
            'blackops.operation.type',
            'blackops.operation.strategy',
            'blackops.attempt.id',
            'blackops.attempt.number',
            'blackops.correlation.id',
            'blackops.causation.id',
            'blackops.schedule.name',
            'blackops.runtime.kind',
            'blackops.result',
            'blackops.actor.origin.type',
            'blackops.actor.origin.id',
            'blackops.actor.authorization.type',
            'blackops.actor.authorization.id',
            'blackops.actor.execution.type',
            'blackops.actor.execution.id',
            'blackops.tenant.type',
            'blackops.tenant.id',
            'error.type',
            'blackops.storage.purpose',
        ];
        $filtered = [];
        foreach ($allowed as $key) {
            /** @var bool|int|float|string|null $value */
            $value = $attributes[$key] ?? null;
            if ($key === 'blackops.result' && (!is_string($value) || !in_array($value, self::RESULTS, strict: true))) {
                continue;
            }
            if (
                $key === 'blackops.runtime.kind'
                && (!is_string($value) || !in_array($value, self::RUNTIME_KINDS, strict: true))
            ) {
                continue;
            }
            if (
                $value !== null
                && in_array(
                    $key,
                    [
                        'blackops.actor.origin.id',
                        'blackops.actor.authorization.id',
                        'blackops.actor.execution.id',
                        'blackops.tenant.id',
                    ],
                    strict: true,
                )
            ) {
                $value = '[masked]';
            }
            if (is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
                $filtered[$key] = $value;
            }
        }
        return $filtered;
    }
}
