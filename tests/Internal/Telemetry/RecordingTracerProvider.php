<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Telemetry;

use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanContext;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\ContextKeys;
use OpenTelemetry\Context\ScopeInterface;
use Throwable;

final class RecordingTracerProvider implements TracerProviderInterface
{
    /** @var list<RecordingSpan> */
    public array $spans = [];
    private int $nextSpan = 1;

    public function getTracer(
        string $name,
        ?string $version = null,
        ?string $schemaUrl = null,
        iterable $attributes = [],
    ): TracerInterface {
        return new RecordingTracer($this);
    }

    public function newSpan(string $name, int $kind, ?ContextInterface $parent, array $attributes): RecordingSpan
    {
        $parentSpan = $parent === null ? null : Span::fromContext($parent);
        $parentContext = $parentSpan?->getContext();
        $traceId = $parentContext?->isValid() === true
            ? $parentContext->getTraceId()
            : str_repeat(dechex(count($this->spans) + 1), 32);
        $spanId = str_pad(dechex($this->nextSpan++), 16, '0', STR_PAD_LEFT);
        $context = SpanContext::create(
            $traceId,
            $spanId,
            $parentContext?->getTraceFlags() ?? 0,
            $parentContext?->getTraceState(),
        );
        $span = new RecordingSpan($name, $kind, $context, $parentContext, $attributes);
        $this->spans[] = $span;

        return $span;
    }
}

final readonly class RecordingTracer implements TracerInterface
{
    public function __construct(
        private RecordingTracerProvider $provider,
    ) {}

    public function spanBuilder(string $spanName): SpanBuilderInterface
    {
        return new RecordingSpanBuilder($this->provider, $spanName);
    }

    public function isEnabled(): bool
    {
        return true;
    }
}

final class RecordingSpanBuilder implements SpanBuilderInterface
{
    private int $kind = 0;
    private ContextInterface|false|null $parent = null;
    /** @var array<string, mixed> */
    private array $attributes = [];

    public function __construct(
        private RecordingTracerProvider $provider,
        private string $name,
    ) {}

    public function setParent(ContextInterface|false|null $context): SpanBuilderInterface
    {
        $this->parent = $context;

        return $this;
    }

    public function addLink(SpanContextInterface $context, iterable $attributes = []): SpanBuilderInterface
    {
        return $this;
    }

    public function setAttribute(string $key, mixed $value): SpanBuilderInterface
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    public function setAttributes(iterable $attributes): SpanBuilderInterface
    {
        /** @var array<string, mixed> $typedAttributes */
        $typedAttributes = is_array($attributes) ? $attributes : iterator_to_array($attributes);
        array_walk($typedAttributes, function (mixed $value, string $key): void {
            if (
                is_bool($value)
                || is_int($value)
                || is_float($value)
                || is_string($value)
                || is_array($value)
                || $value === null
            ) {
                $this->attributes[$key] = $value;
            }
        });

        return $this;
    }

    public function setStartTimestamp(int $timestampNanos): SpanBuilderInterface
    {
        return $this;
    }

    public function setSpanKind(int $spanKind): SpanBuilderInterface
    {
        $this->kind = $spanKind;

        return $this;
    }

    public function startSpan(): SpanInterface
    {
        $parent = $this->parent === false ? null : $this->parent;

        return $this->provider->newSpan($this->name, $this->kind, $parent, $this->attributes);
    }
}

final class RecordingSpan implements SpanInterface
{
    public bool $ended = false;
    public int $recordExceptionCalls = 0;
    public string $status = 'Unset';
    /** @var array<string, mixed> */
    public array $attributes;

    public function __construct(
        public readonly string $name,
        public readonly int $kind,
        private SpanContextInterface $context,
        public readonly ?SpanContextInterface $parent,
        array $attributes,
    ) {
        /** @var array<string, mixed> $attributes */
        $this->attributes = $attributes;
    }

    public static function fromContext(ContextInterface $context): SpanInterface
    {
        return Span::fromContext($context);
    }

    public static function getCurrent(): SpanInterface
    {
        return Span::getCurrent();
    }

    public static function getInvalid(): SpanInterface
    {
        return Span::getInvalid();
    }

    public static function wrap(SpanContextInterface $spanContext): SpanInterface
    {
        return Span::wrap($spanContext);
    }

    public function getContext(): SpanContextInterface
    {
        return $this->context;
    }

    public function isRecording(): bool
    {
        return !$this->ended;
    }

    public function setAttribute(string $key, bool|int|float|string|array|null $value): SpanInterface
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    public function setAttributes(iterable $attributes): SpanInterface
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }

        return $this;
    }

    public function addLink(SpanContextInterface $context, iterable $attributes = []): SpanInterface
    {
        return $this;
    }

    public function addEvent(string $name, iterable $attributes = [], ?int $timestamp = null): SpanInterface
    {
        return $this;
    }

    public function recordException(Throwable $exception, iterable $attributes = []): SpanInterface
    {
        ++$this->recordExceptionCalls;

        return $this;
    }

    public function updateName(string $name): SpanInterface
    {
        return $this;
    }

    public function setStatus(string $code, ?string $description = null): SpanInterface
    {
        $this->status = $code;

        return $this;
    }

    public function end(?int $endEpochNanos = null): void
    {
        $this->ended = true;
    }

    public function activate(): ScopeInterface
    {
        return Context::getCurrent()->withContextValue($this)->activate();
    }

    public function storeInContext(ContextInterface $context): ContextInterface
    {
        return $context->with(ContextKeys::span(), $this);
    }
}
