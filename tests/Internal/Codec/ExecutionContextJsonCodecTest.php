<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Codec;

use BlackOps\Core\Codec\OperationCodecException;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\ScheduleContext;
use BlackOps\Idempotency\IdempotencyKey;
use BlackOps\Internal\Codec\ExecutionContextJsonCodec;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExecutionContextJsonCodecTest extends TestCase
{
    public function testHashRoundTripsWithoutRawKey(): void
    {
        $context = new ExecutionContext(
            OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687701'),
            new DateTimeImmutable('2026-07-23T00:00:00Z'),
            CorrelationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687702'),
            idempotencyKeyHash: new IdempotencyKey('raw-secret-key')->hash(),
        );

        $encoded = new ExecutionContextJsonCodec()->encode($context);
        $decoded = new ExecutionContextJsonCodec()->decode($encoded);

        self::assertStringContainsString('idempotency_key_hash', $encoded);
        self::assertStringNotContainsString('raw-secret-key', $encoded);
        self::assertTrue($context->idempotencyKeyHash()?->equals($decoded->idempotencyKeyHash()));
    }

    public function testMissingHashFieldRemainsBackwardCompatible(): void
    {
        $context = '{"operation_id":"019f32ab-2be0-7b38-a0a7-1ab2f9687701","received_at":"2026-07-23T00:00:00.000000Z","correlation_id":"019f32ab-2be0-7b38-a0a7-1ab2f9687702","causation_id":null,"attempt":null,"deadline":null}';

        self::assertNull(
            new ExecutionContextJsonCodec()
                ->decode($context)
                ->idempotencyKeyHash(),
        );
    }

    public function testUnknownVersionInvalidDigestAndUnexpectedFieldFail(): void
    {
        $base = [
            'operation_id' => '019f32ab-2be0-7b38-a0a7-1ab2f9687701',
            'received_at' => '2026-07-23T00:00:00Z',
            'correlation_id' => '019f32ab-2be0-7b38-a0a7-1ab2f9687702',
            'causation_id' => null,
            'attempt' => null,
            'deadline' => null,
        ];

        foreach ([
            ['version' => 2, 'digest' => str_repeat('a', times: 64)],
            ['version' => 1, 'digest' => 'bad'],
            ['version' => 1, 'digest' => str_repeat('a', times: 64), 'extra' => true],
        ] as $hash) {
            try {
                new ExecutionContextJsonCodec()->decode(json_encode(
                    $base + ['idempotency_key_hash' => $hash],
                    JSON_THROW_ON_ERROR,
                ));
                self::fail('Expected invalid idempotency hash failure.');
            } catch (OperationCodecException) {
                self::assertTrue(true);
            }
        }
    }

    public function testScheduleContextRoundTripsAndKeepsCanonicalUtcPrecision(): void
    {
        $context = new ExecutionContext(
            OperationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687701'),
            new DateTimeImmutable('2026-07-23T00:00:00.123456Z'),
            CorrelationId::fromString('019f32ab-2be0-7b38-a0a7-1ab2f9687702'),
            schedule: new ScheduleContext(
                'reports.daily',
                new DateTimeImmutable('2026-07-22T18:00:00.654321+09:00'),
                'Asia/Tokyo',
            ),
        );

        $encoded = new ExecutionContextJsonCodec()->encode($context);
        $decoded = new ExecutionContextJsonCodec()->decode($encoded);

        self::assertStringContainsString('"scheduled_at":"2026-07-22T09:00:00.654321Z"', $encoded);
        self::assertSame('reports.daily', $decoded->schedule()?->name());
        self::assertSame('Asia/Tokyo', $decoded->schedule()?->timezone());
        self::assertSame(
            '2026-07-22T09:00:00.654321Z',
            $decoded->schedule()?->scheduledAt()->format('Y-m-d\\TH:i:s.u\\Z'),
        );
    }

    #[DataProvider('invalidSchedules')]
    public function testScheduleObjectInvalidShapeFailsSafely(array $schedule): void
    {
        $payload = [
            'operation_id' => '019f32ab-2be0-7b38-a0a7-1ab2f9687701',
            'received_at' => '2026-07-23T00:00:00.000000Z',
            'correlation_id' => '019f32ab-2be0-7b38-a0a7-1ab2f9687702',
            'causation_id' => null,
            'attempt' => null,
            'deadline' => null,
            'schedule' => $schedule,
        ];

        $this->expectException(OperationCodecException::class);
        new ExecutionContextJsonCodec()->decode(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** @return iterable<string, array{schedule: array<string, mixed>}> */
    public static function invalidSchedules(): iterable
    {
        yield 'unknown field' => ['schedule' => [
            'name' => 'reports.daily',
            'scheduled_at' => '2026-07-22T09:00:00.000000Z',
            'timezone' => 'UTC',
            'extra' => true,
        ]];
        yield 'missing field' => ['schedule' => [
            'name' => 'reports.daily',
            'scheduled_at' => '2026-07-22T09:00:00.000000Z',
        ]];
        yield 'invalid name' => ['schedule' => [
            'name' => 'Reports Daily',
            'scheduled_at' => '2026-07-22T09:00:00.000000Z',
            'timezone' => 'UTC',
        ]];
        yield 'invalid timestamp' => ['schedule' => [
            'name' => 'reports.daily',
            'scheduled_at' => 'not-a-time',
            'timezone' => 'UTC',
        ]];
        yield 'invalid timezone' => ['schedule' => [
            'name' => 'reports.daily',
            'scheduled_at' => '2026-07-22T09:00:00.000000Z',
            'timezone' => 'Mars/Olympus',
        ]];
    }
}
