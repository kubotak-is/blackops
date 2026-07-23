<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Codec;

use BlackOps\Core\Codec\OperationCodecException;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Idempotency\IdempotencyKey;
use BlackOps\Internal\Codec\ExecutionContextJsonCodec;
use DateTimeImmutable;
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
}
