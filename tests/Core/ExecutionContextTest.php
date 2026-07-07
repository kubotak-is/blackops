<?php

declare(strict_types=1);

namespace BlackOps\Tests\Core;

use BlackOps\Core\AttemptContext;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\CausationId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ExecutionContextTest extends TestCase
{
    private const OPERATION_V7 = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';
    private const CORRELATION_V7 = '019f32ac-2be0-7b38-a0a7-1ab2f9687697';
    private const CAUSATION_V7 = '019f32ad-2be0-7b38-a0a7-1ab2f9687697';
    private const ATTEMPT_V7 = '019f32ae-2be0-7b38-a0a7-1ab2f9687697';

    public function testIsFinalReadonlyClassMarkedPublicApi(): void
    {
        $reflection = new ReflectionClass(ExecutionContext::class);

        self::assertTrue($reflection->isFinal(), 'ExecutionContext must be final.');
        self::assertTrue($reflection->isReadOnly(), 'ExecutionContext must be readonly.');
        self::assertCount(
            1,
            $reflection->getAttributes(PublicApi::class),
            'ExecutionContext must be marked with #[PublicApi].',
        );
    }

    public function testConstructorDefaultsForOptionalFieldsAreNull(): void
    {
        $operation = OperationId::fromString(self::OPERATION_V7);
        $correlation = CorrelationId::fromString(self::CORRELATION_V7);
        $receivedAt = new DateTimeImmutable('2026-07-02T12:34:56.123456', new DateTimeZone('UTC'));

        $context = new ExecutionContext($operation, $receivedAt, $correlation);

        self::assertSame($operation, $context->operationId());
        self::assertSame($receivedAt, $context->receivedAt());
        self::assertSame($correlation, $context->correlationId());
        self::assertNull($context->causationId());
        self::assertNull($context->attempt());
        self::assertNull($context->deadline());
    }

    public function testGettersReturnConstructorValues(): void
    {
        $operation = OperationId::fromString(self::OPERATION_V7);
        $correlation = CorrelationId::fromString(self::CORRELATION_V7);
        $causation = CausationId::fromString(self::CAUSATION_V7);
        $receivedAt = new DateTimeImmutable('2026-07-02T12:34:56.123456', new DateTimeZone('UTC'));
        $deadline = new DateTimeImmutable('2026-07-03T00:00:00.000000', new DateTimeZone('UTC'));
        $attempt = new AttemptContext(
            AttemptId::fromString(self::ATTEMPT_V7),
            2,
            new DateTimeImmutable('2026-07-02T12:35:00.000000', new DateTimeZone('UTC')),
        );

        $context = new ExecutionContext($operation, $receivedAt, $correlation, $causation, $attempt, $deadline);

        self::assertSame($operation, $context->operationId());
        self::assertSame($correlation, $context->correlationId());
        self::assertSame($causation, $context->causationId());
        self::assertSame($attempt, $context->attempt());
        self::assertSame($deadline, $context->deadline());
    }

    public function testReceivedAtIsNormalizedToUtc(): void
    {
        $tokyoTime = new DateTimeImmutable('2026-07-02T21:34:56.123456', new DateTimeZone('Asia/Tokyo'));

        $context = new ExecutionContext(
            OperationId::fromString(self::OPERATION_V7),
            $tokyoTime,
            CorrelationId::fromString(self::CORRELATION_V7),
        );

        self::assertSame('UTC', $context->receivedAt()->getTimezone()->getName());
        self::assertSame('2026-07-02T12:34:56.123456Z', $context->receivedAt()->format('Y-m-d\TH:i:s.u\Z'));
    }

    public function testDeadlineIsNormalizedToUtc(): void
    {
        $tokyoDeadline = new DateTimeImmutable('2026-07-03T09:00:00.000000', new DateTimeZone('Asia/Tokyo'));

        $context = new ExecutionContext(
            OperationId::fromString(self::OPERATION_V7),
            new DateTimeImmutable('2026-07-02T12:34:56.123456Z', new DateTimeZone('UTC')),
            CorrelationId::fromString(self::CORRELATION_V7),
            null,
            null,
            $tokyoDeadline,
        );

        self::assertSame('UTC', $context->deadline()->getTimezone()->getName());
        self::assertSame('2026-07-03T00:00:00.000000Z', $context->deadline()->format('Y-m-d\TH:i:s.u\Z'));
    }

    public function testNullDeadlineRemainsNull(): void
    {
        $context = new ExecutionContext(
            OperationId::fromString(self::OPERATION_V7),
            new DateTimeImmutable('2026-07-02T12:34:56.123456Z', new DateTimeZone('UTC')),
            CorrelationId::fromString(self::CORRELATION_V7),
        );

        self::assertNull($context->deadline());
    }

    public function testAlreadyUtcTimeIsPreserved(): void
    {
        $utcTime = new DateTimeImmutable('2026-07-02T12:34:56.123456Z', new DateTimeZone('UTC'));

        $context = new ExecutionContext(
            OperationId::fromString(self::OPERATION_V7),
            $utcTime,
            CorrelationId::fromString(self::CORRELATION_V7),
        );

        self::assertSame('UTC', $context->receivedAt()->getTimezone()->getName());
        self::assertSame('2026-07-02T12:34:56.123456Z', $context->receivedAt()->format('Y-m-d\TH:i:s.u\Z'));
    }
}
