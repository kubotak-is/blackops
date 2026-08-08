<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Execution;

use BlackOps\Core\Execution\Inline;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationValue;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Telemetry\TelemetryTracer;
use DateTimeImmutable;
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
