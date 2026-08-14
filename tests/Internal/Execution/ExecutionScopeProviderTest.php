<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Execution;

use BlackOps\Core\Execution\Inline;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Telemetry\TelemetryMetrics;
use BlackOps\Internal\Telemetry\TelemetryTracer;
use BlackOps\Tests\Internal\Telemetry\RecordingMeterProvider;
use DateTimeImmutable;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ScopeInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExecutionScopeProviderTest extends TestCase
{
    private const ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';

    public function testCurrentIsAvailableOnlyInsideScope(): void
    {
        $scope = new ExecutionScopeProvider();
        $envelope = self::envelope('outer');

        self::assertNull($scope->current());

        $result = $scope->run($envelope, static function () use ($scope, $envelope): string {
            self::assertSame($envelope, $scope->current());

            return 'done';
        });

        self::assertSame('done', $result);
        self::assertNull($scope->current());
    }

    public function testNestedScopeRestoresParent(): void
    {
        $scope = new ExecutionScopeProvider();
        $parent = self::envelope('parent');
        $child = self::envelope('child');

        $scope->run($parent, static function () use ($scope, $parent, $child): void {
            self::assertSame($parent, $scope->current());

            $scope->run($child, static function () use ($scope, $child): void {
                self::assertSame($child, $scope->current());
            });

            self::assertSame($parent, $scope->current());
        });

        self::assertNull($scope->current());
    }

    public function testScopeIsClearedAfterException(): void
    {
        $scope = new ExecutionScopeProvider();

        try {
            $scope->run(self::envelope('throwing'), static function (): never {
                throw new RuntimeException('boom');
            });
            self::fail('Expected scope callback exception.');
        } catch (RuntimeException) {
        }

        self::assertNull($scope->current());
    }

    public function testTelemetryScopeDetachesAndEndsAfterNestedFailure(): void
    {
        $span = $this->createMock(SpanInterface::class);
        $active = $this->createMock(ScopeInterface::class);
        $span->expects(self::once())->method('activate')->willReturn($active);
        $active->expects(self::once())->method('detach');
        $span->expects(self::once())->method('setStatus')->with(StatusCode::STATUS_ERROR)->willReturnSelf();
        $span->expects(self::exactly(2))->method('setAttribute')->willReturnSelf();
        $span->expects(self::once())->method('end');
        $builder = $this->createMock(SpanBuilderInterface::class);
        $builder->method('setSpanKind')->willReturnSelf();
        $builder->method('setAttributes')->willReturnSelf();
        $builder->method('startSpan')->willReturn($span);
        $tracer = $this->createMock(TracerInterface::class);
        $tracer->method('spanBuilder')->with('blackops.operation.execute')->willReturn($builder);
        $provider = $this->createMock(TracerProviderInterface::class);
        $provider->method('getTracer')->willReturn($tracer);
        $scope = new ExecutionScopeProvider(new TelemetryTracer($provider));

        try {
            $scope->run(
                self::envelope('telemetry'),
                static function (): never {
                    throw new RuntimeException('primary');
                },
                'test.operation',
            );
            self::fail('Expected primary failure.');
        } catch (RuntimeException $failure) {
            self::assertSame('primary', $failure->getMessage());
        }

        self::assertNull($scope->current());
    }

    public function testRejectedOperationResultIsRecordedAsRejected(): void
    {
        $provider = new RecordingMeterProvider();
        $scope = new ExecutionScopeProvider(metrics: new TelemetryMetrics($provider, ['test.operation']));

        $scope->run(
            self::envelope('rejected'),
            static fn(): OperationResult => OperationResult::rejected(RejectionReason::businessRule('denied')),
            'test.operation',
        );

        self::assertSame('rejected', $provider->instruments[0]->records[0]['attributes']['blackops.result']);
        self::assertSame(1, $provider->instruments[1]->records[0]['amount']);
        self::assertSame(-1, $provider->instruments[1]->records[1]['amount']);
    }

    public function testThrowingMeterProviderPreservesCallbackResultsAndStackCleanup(): void
    {
        $provider = $this->createMock(MeterProviderInterface::class);
        $provider->method('getMeter')->willThrowException(new RuntimeException('meter unavailable'));
        $scope = new ExecutionScopeProvider(metrics: new TelemetryMetrics($provider, ['test.operation']));

        $result = $scope->run(
            self::envelope('rejected'),
            static fn(): OperationResult => OperationResult::rejected(RejectionReason::businessRule('denied')),
            'test.operation',
        );
        self::assertTrue($result->isRejected());
        self::assertNull($scope->current());

        try {
            $scope->run(
                self::envelope('throwing'),
                static function (): never {
                    throw new RuntimeException('callback marker');
                },
                'test.operation',
            );
            self::fail('Expected callback failure.');
        } catch (RuntimeException $failure) {
            self::assertSame('callback marker', $failure->getMessage());
        }
        self::assertNull($scope->current());
        self::assertNull($scope->currentOperationTypeId());
    }

    private static function envelope(string $message): OperationEnvelope
    {
        return new OperationEnvelope(
            new ScopedOperation(),
            new ScopedValue($message),
            new ExecutionContext(
                OperationId::fromString(self::ID),
                new DateTimeImmutable('2026-07-07T00:00:00Z'),
                CorrelationId::fromString(self::ID),
            ),
            new Inline(),
        );
    }
}

final readonly class ScopedOperation implements Operation {}

final readonly class ScopedValue implements OperationValue
{
    public function __construct(
        public string $message,
    ) {}
}
