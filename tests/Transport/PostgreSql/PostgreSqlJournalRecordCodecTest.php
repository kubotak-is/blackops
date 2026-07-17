<?php

declare(strict_types=1);

namespace BlackOps\Tests\Transport\PostgreSql;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use BlackOps\Transport\PostgreSql\PostgreSqlJournalRecordCodec;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PostgreSqlJournalRecordCodecTest extends TestCase
{
    private const ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';

    public function testRoundTripsCanonicalActorIdsAndTypesWithoutMasking(): void
    {
        $codec = new PostgreSqlJournalRecordCodec();
        $record = $this->record(
            new ActorContext(
                new ActorRef('user-origin-123', 'user'),
                new ActorRef('user-authorization-456', 'user'),
                new ActorRef('http-runtime-789', 'system'),
            ),
        );

        $encoded = $codec->encode($record);
        $decoded = $codec->decode($encoded);
        $actors = $decoded->operation->actorContext;

        self::assertStringContainsString('user-origin-123', $encoded);
        self::assertStringContainsString('user-authorization-456', $encoded);
        self::assertStringContainsString('http-runtime-789', $encoded);
        self::assertStringNotContainsString('[masked]', $encoded);
        self::assertSame('user-origin-123', $actors?->origin()?->id());
        self::assertSame('user', $actors?->origin()?->type());
        self::assertSame('user-authorization-456', $actors?->authorization()?->id());
        self::assertSame('user', $actors?->authorization()?->type());
        self::assertSame('http-runtime-789', $actors?->execution()->id());
        self::assertSame('system', $actors?->execution()->type());
    }

    public function testEncodesNullActorsAndDecodesLegacyPayloadWithoutActors(): void
    {
        $codec = new PostgreSqlJournalRecordCodec();
        $encoded = $codec->encode($this->record());
        /** @var array<string, mixed> $payload */
        $payload = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);
        /** @var array<string, mixed> $operation */
        $operation = $payload['operation'];

        self::assertArrayHasKey('actors', $operation);
        self::assertNull($operation['actors']);

        unset($operation['actors']);
        $payload['operation'] = $operation;
        $legacy = $codec->decode(json_encode($payload, JSON_THROW_ON_ERROR));

        self::assertNull($legacy->operation->actorContext);
    }

    #[DataProvider('invalidActors')]
    public function testRejectsInvalidActorStructures(mixed $actors): void
    {
        $codec = new PostgreSqlJournalRecordCodec();
        /** @var array<string, mixed> $payload */
        $payload = json_decode($codec->encode($this->record()), true, flags: JSON_THROW_ON_ERROR);
        /** @var array<string, mixed> $operation */
        $operation = $payload['operation'];
        $operation['actors'] = $actors;
        $payload['operation'] = $operation;

        $this->expectException(RuntimeException::class);

        $codec->decode(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return iterable<string, array{actors: mixed}>
     */
    public static function invalidActors(): iterable
    {
        yield 'non-object actor context' => ['actors' => 'invalid'];
        yield 'missing execution field' => ['actors' => [
            'origin' => null,
            'authorization' => null,
        ]];
        yield 'unknown role field' => [
            'actors' => [
                'origin' => null,
                'authorization' => null,
                'execution' => ['id' => 'runtime', 'type' => 'system'],
                'role' => 'admin',
            ],
        ];
        yield 'null execution actor' => ['actors' => [
            'origin' => null,
            'authorization' => null,
            'execution' => null,
        ]];
        yield 'non-object actor' => [
            'actors' => [
                'origin' => 'user-123',
                'authorization' => null,
                'execution' => ['id' => 'runtime', 'type' => 'system'],
            ],
        ];
        yield 'missing actor type' => [
            'actors' => [
                'origin' => null,
                'authorization' => null,
                'execution' => ['id' => 'runtime'],
            ],
        ];
        yield 'credential actor field' => [
            'actors' => [
                'origin' => null,
                'authorization' => null,
                'execution' => ['id' => 'runtime', 'type' => 'system', 'credential' => 'secret'],
            ],
        ];
        yield 'non-string actor id' => [
            'actors' => [
                'origin' => null,
                'authorization' => null,
                'execution' => ['id' => 123, 'type' => 'system'],
            ],
        ];
        yield 'blank actor type' => [
            'actors' => [
                'origin' => null,
                'authorization' => null,
                'execution' => ['id' => 'runtime', 'type' => '  '],
            ],
        ];
        yield 'empty actor id' => [
            'actors' => [
                'origin' => null,
                'authorization' => null,
                'execution' => ['id' => '', 'type' => 'system'],
            ],
        ];
        yield 'padded actor id' => [
            'actors' => [
                'origin' => null,
                'authorization' => null,
                'execution' => ['id' => ' runtime ', 'type' => 'system'],
            ],
        ];
    }

    private function record(?ActorContext $actors = null): JournalRecord
    {
        return new JournalRecord(
            JournalRecordId::fromString(self::ID),
            1,
            JournalEvent::OperationReceived,
            new DateTimeImmutable('2026-07-17T00:00:00.123456Z'),
            1,
            new JournalOperation(
                OperationId::fromString(self::ID),
                'codec.actor',
                1,
                'inline',
                CorrelationId::fromString(self::ID),
                actorContext: $actors,
            ),
            null,
            new EmptyJournalData(),
        );
    }
}
