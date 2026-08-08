<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Telemetry;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationValue;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Telemetry\TelemetryTracer;
use BlackOps\Telemetry\TelemetryContext;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanContext;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\API\Trace\TraceState;
use OpenTelemetry\Context\Context;
use PHPUnit\Framework\TestCase;

final class TelemetryTracerTest extends TestCase
{
    public function testStartsAProducerSpanWithTheRemoteParentAndSafeAttributes(): void
    {
        $span = $this->createMock(SpanInterface::class);
        $scope = $this->createMock(\OpenTelemetry\Context\ScopeInterface::class);
        $span->expects(self::once())->method('activate')->willReturn($scope);
        $span->expects(self::once())->method('end');
        $builder = $this->createMock(SpanBuilderInterface::class);
        $builder->expects(self::once())->method('setSpanKind')->with(SpanKind::KIND_PRODUCER)->willReturnSelf();
        $builder->expects(self::once())->method('setParent')->willReturnSelf();
        $builder
            ->expects(self::once())
            ->method('setAttributes')
            ->with(self::callback(
                static fn(array $attributes): bool => $attributes === ['blackops.operation.id' => 'safe'],
            ))
            ->willReturnSelf();
        $builder->expects(self::once())->method('startSpan')->willReturn($span);
        $tracer = $this->createMock(TracerInterface::class);
        $tracer->method('spanBuilder')->with('blackops.operation.accept')->willReturn($builder);
        $provider = $this->createMock(TracerProviderInterface::class);
        $provider->method('getTracer')->with(TelemetryTracer::SCOPE, TelemetryTracer::VERSION)->willReturn($tracer);

        $started = new TelemetryTracer($provider)->start(
            'blackops.operation.accept',
            SpanKind::KIND_PRODUCER,
            new TelemetryContext('00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01'),
            ['blackops.operation.id' => 'safe', 'private.value' => 'filtered'],
        );
        $started->end();
    }

    public function testSpanEndsAndDetachesWhenPrimaryCallbackFails(): void
    {
        $span = $this->createMock(SpanInterface::class);
        $scope = $this->createMock(\OpenTelemetry\Context\ScopeInterface::class);
        $span->expects(self::once())->method('activate')->willReturn($scope);
        $scope->expects(self::once())->method('detach');
        $span->expects(self::once())->method('setStatus')->with(StatusCode::STATUS_ERROR)->willReturnSelf();
        $span->expects(self::exactly(2))->method('setAttribute')->willReturnSelf();
        $span->expects(self::once())->method('end');
        $builder = $this->createMock(SpanBuilderInterface::class);
        $builder->method('setSpanKind')->willReturnSelf();
        $builder->method('setAttributes')->willReturnSelf();
        $builder->method('startSpan')->willReturn($span);
        $tracer = $this->createMock(TracerInterface::class);
        $tracer->method('spanBuilder')->willReturn($builder);
        $provider = $this->createMock(TracerProviderInterface::class);
        $provider->method('getTracer')->willReturn($tracer);
        $started = new TelemetryTracer($provider)->start('blackops.operation.execute');
        $this->expectException(\RuntimeException::class);
        try {
            throw new \RuntimeException('primary');
        } catch (\RuntimeException $failure) {
            $started->fail($failure);
            throw $failure;
        } finally {
            $started->end();
        }
    }

    public function testActiveFrameworkSpanIsPreferredOverPersistedRemoteParent(): void
    {
        $activeContext = SpanContext::create('4bf92f3577b34da6a3ce929d0e0e4736', '00f067aa0ba902b7');
        $active = Span::wrap($activeContext);
        $activeScope = Context::getCurrent()->withContextValue($active)->activate();
        try {
            $span = $this->createMock(SpanInterface::class);
            $scope = $this->createMock(\OpenTelemetry\Context\ScopeInterface::class);
            $span->method('activate')->willReturn($scope);
            $builder = $this->createMock(SpanBuilderInterface::class);
            $builder->method('setSpanKind')->willReturnSelf();
            $builder
                ->expects(self::once())
                ->method('setParent')
                ->with(self::callback(
                    static fn(Context $parent): bool => (
                        Span::fromContext($parent)->getContext()->getSpanId() === '00f067aa0ba902b7'
                    ),
                ))
                ->willReturnSelf();
            $builder->method('setAttributes')->willReturnSelf();
            $builder->method('startSpan')->willReturn($span);
            $tracer = $this->createMock(TracerInterface::class);
            $tracer->method('spanBuilder')->willReturn($builder);
            $provider = $this->createMock(TracerProviderInterface::class);
            $provider->method('getTracer')->willReturn($tracer);

            $started = new TelemetryTracer($provider)->start(
                'blackops.operation.execute',
                parent: new TelemetryContext('00-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa-bbbbbbbbbbbbbbbb-01'),
            );
            $started->end();
        } finally {
            $activeScope->detach();
        }
    }

    public function testResultAttributeAcceptsOnlyFiniteValues(): void
    {
        $builder = $this->createMock(SpanBuilderInterface::class);
        $builder->method('setSpanKind')->willReturnSelf();
        $builder
            ->expects(self::once())
            ->method('setAttributes')
            ->with([
                'blackops.result' => 'completed',
            ])
            ->willReturnSelf();
        $span = $this->createMock(SpanInterface::class);
        $span->method('activate')->willReturn($this->createMock(\OpenTelemetry\Context\ScopeInterface::class));
        $builder->method('startSpan')->willReturn($span);
        $tracer = $this->createMock(TracerInterface::class);
        $tracer->method('spanBuilder')->willReturn($builder);
        $provider = $this->createMock(TracerProviderInterface::class);
        $provider->method('getTracer')->willReturn($tracer);

        new TelemetryTracer($provider)->start('blackops.operation.execute', attributes: [
            'blackops.result' => 'completed',
            'blackops.status' => 'free-form',
        ])->end();
    }

    public function testCurrentContextPreservesTraceFlagsAndTracestate(): void
    {
        $span = Span::wrap(SpanContext::create(
            '4bf92f3577b34da6a3ce929d0e0e4736',
            '00f067aa0ba902b7',
            1,
            new TraceState('vendor=value'),
        ));
        $scope = $span->activate();
        try {
            $context = new TelemetryTracer()->currentContext();
            self::assertNotNull($context);
            self::assertSame('00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01', $context->traceparent());
            self::assertSame('vendor=value', $context->tracestate());
        } finally {
            $scope->detach();
        }
    }

    public function testActivationFailureEndsPartialSpanAndReturnsNoopScope(): void
    {
        $span = $this->createMock(SpanInterface::class);
        $span
            ->expects(self::once())
            ->method('activate')
            ->willThrowException(new \RuntimeException('activation'));
        $span->expects(self::once())->method('end');
        $builder = $this->createMock(SpanBuilderInterface::class);
        $builder->method('setSpanKind')->willReturnSelf();
        $builder->method('setAttributes')->willReturnSelf();
        $builder->method('startSpan')->willReturn($span);
        $tracer = $this->createMock(TracerInterface::class);
        $tracer->method('spanBuilder')->willReturn($builder);
        $provider = $this->createMock(TracerProviderInterface::class);
        $provider->method('getTracer')->willReturn($tracer);

        $scope = new TelemetryTracer($provider)->start('blackops.operation.execute');
        $scope->result('completed');
        $scope->end();
    }

    public function testOperationProjectsFullMaskedActorAndTenantAttributes(): void
    {
        $span = $this->createMock(SpanInterface::class);
        $span->method('activate')->willReturn($this->createMock(\OpenTelemetry\Context\ScopeInterface::class));
        $builder = $this->createMock(SpanBuilderInterface::class);
        $builder->method('setSpanKind')->willReturnSelf();
        $builder
            ->expects(self::once())
            ->method('setAttributes')
            ->with(self::callback(
                static fn(array $attributes): bool => (
                    $attributes['blackops.actor.origin.type'] === 'user'
                    && $attributes['blackops.actor.origin.id'] === '[masked]'
                    && $attributes['blackops.actor.authorization.type'] === 'user'
                    && $attributes['blackops.actor.authorization.id'] === '[masked]'
                    && $attributes['blackops.actor.execution.type'] === 'worker'
                    && $attributes['blackops.actor.execution.id'] === '[masked]'
                    && $attributes['blackops.tenant.type'] === 'account'
                    && $attributes['blackops.tenant.id'] === '[masked]'
                ),
            ))
            ->willReturnSelf();
        $builder->method('startSpan')->willReturn($span);
        $tracer = $this->createMock(TracerInterface::class);
        $tracer->method('spanBuilder')->willReturn($builder);
        $provider = $this->createMock(TracerProviderInterface::class);
        $provider->method('getTracer')->willReturn($tracer);

        $context = new ExecutionContext(
            OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687697'),
            new \DateTimeImmutable('2026-07-07T00:00:00Z'),
            CorrelationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687697'),
            actorContext: new ActorContext(
                new ActorRef('raw-origin', 'user'),
                new ActorRef('raw-auth', 'user'),
                new ActorRef('raw-execution', 'worker'),
            ),
            tenant: new TenantRef('account', 'raw-tenant'),
        );
        $scope = new TelemetryTracer($provider)->operation(
            new OperationEnvelope(new TelemetryOperation(), new TelemetryValue(), $context, new Inline()),
            'telemetry.test',
        );
        $scope->end();
    }

    public function testRecordingProviderCapturesSpanContractWithoutExceptionRecording(): void
    {
        $provider = new RecordingTracerProvider();
        $scope = new TelemetryTracer($provider)->start(
            'blackops.operation.accept',
            TelemetryTracer::KIND_PRODUCER,
            new TelemetryContext('00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01'),
            ['blackops.result' => 'completed'],
        );
        $scope->end();
        self::assertCount(1, $provider->spans);
        $span = $provider->spans[0];
        self::assertSame('blackops.operation.accept', $span->name);
        self::assertSame(TelemetryTracer::KIND_PRODUCER, $span->kind);
        self::assertSame('4bf92f3577b34da6a3ce929d0e0e4736', $span->parent?->getTraceId());
        self::assertSame('00f067aa0ba902b7', $span->parent?->getSpanId());
        self::assertSame('completed', $span->attributes['blackops.result']);
        self::assertTrue($span->ended);
        self::assertSame(0, $span->recordExceptionCalls);
    }
}

final readonly class TelemetryOperation implements Operation {}

final readonly class TelemetryValue implements OperationValue {}
