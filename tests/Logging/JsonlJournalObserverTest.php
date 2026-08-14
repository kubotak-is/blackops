<?php

declare(strict_types=1);

namespace BlackOps\Tests\Logging;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Journal\Exception\JournalObservationFailed;
use BlackOps\Journal\JournalAttempt;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\ObservedJournalRecord;
use BlackOps\Logging\JsonlJournalObserver;
use BlackOps\Logging\JsonlJournalRecordEncoder;
use BlackOps\Telemetry\TelemetryCorrelation;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class JsonlJournalObserverTest extends TestCase
{
    private const ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';

    public function testWritesOneStructuredJsonLinePerObservedRecord(): void
    {
        $stream = self::stream();
        $observer = new JsonlJournalObserver($stream);

        $observer->observe(self::record());
        $observer->flush();

        rewind($stream);
        $line = fgets($stream);
        self::assertIsString($line);
        self::assertStringEndsWith("\n", $line);

        $payload = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame(1, $payload['schemaVersion']);
        self::assertSame('journal', $payload['kind']);
        self::assertSame('operation.received', $payload['event']);
        self::assertSame('2026-07-06T23:00:01.123456Z', $payload['occurredAt']);
        self::assertSame('dispatch.test', $payload['operation']['type']);
        self::assertSame(
            [
                'origin' => ['id' => '[masked]', 'type' => 'user'],
                'authorization' => null,
                'execution' => ['id' => '[masked]', 'type' => 'system'],
            ],
            $payload['operation']['actors'],
        );
        self::assertSame('2026-07-06T23:00:00.123456Z', $payload['attempt']['startedAt']);
        self::assertSame(['message' => 'hello'], $payload['data']['value']);
        self::assertFalse(fgets($stream));
    }

    public function testPublicApiShapes(): void
    {
        foreach ([JsonlJournalObserver::class, JsonlJournalRecordEncoder::class] as $type) {
            $reflection = new ReflectionClass($type);
            self::assertTrue($reflection->isFinal());
            self::assertCount(1, $reflection->getAttributes(PublicApi::class));
        }
    }

    public function testTelemetryIsTopLevelSafeCorrelationAndNeverNestedInOperation(): void
    {
        $record = self::record();
        $operation = new JournalOperation(
            $record->operation->id,
            $record->operation->type,
            $record->operation->schemaVersion,
            $record->operation->strategy,
            $record->operation->correlationId,
            telemetry: new TelemetryCorrelation('4bf92f3577b34da6a3ce929d0e0e4736', '00f067aa0ba902b7', true),
        );
        $record = new ObservedJournalRecord(
            $record->recordId,
            $record->schemaVersion,
            $record->event,
            $record->occurredAt,
            $record->sequence,
            $operation,
            $record->attempt,
            $record->data,
        );
        $payload = json_decode(new JsonlJournalRecordEncoder()->encode($record), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(
            [
                'traceId' => '4bf92f3577b34da6a3ce929d0e0e4736',
                'spanId' => '00f067aa0ba902b7',
                'sampled' => true,
            ],
            $payload['telemetry'],
        );
        self::assertArrayNotHasKey('telemetry', $payload['operation']);
    }

    public function testJsonlMasksTenantAndActorIdsAtEncoderBoundary(): void
    {
        $record = self::record();
        $operation = new JournalOperation(
            $record->operation->id,
            $record->operation->type,
            $record->operation->schemaVersion,
            $record->operation->strategy,
            $record->operation->correlationId,
            actorContext: new \BlackOps\Core\ActorContext(
                new \BlackOps\Core\ActorRef('actor-secret-id', 'user'),
                null,
                new \BlackOps\Core\ActorRef('runtime-secret-id', 'system'),
            ),
            tenant: new TenantRef('account', 'tenant-secret-id'),
        );
        $record = new ObservedJournalRecord(
            $record->recordId,
            $record->schemaVersion,
            $record->event,
            $record->occurredAt,
            $record->sequence,
            $operation,
            $record->attempt,
            $record->data,
        );
        $jsonl = new JsonlJournalRecordEncoder()->encode($record);
        self::assertStringNotContainsString('tenant-secret-id', $jsonl);
        self::assertStringNotContainsString('actor-secret-id', $jsonl);
        self::assertStringNotContainsString('runtime-secret-id', $jsonl);
        self::assertStringContainsString('"id":"[masked]"', $jsonl);
    }

    public function testInvalidStreamIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new JsonlJournalObserver('not-a-stream');
    }

    public function testReadOnlyStreamWriteFailsAsObservationFailure(): void
    {
        $stream = fopen('php://temp', 'rb');
        self::assertIsResource($stream);

        $observer = new JsonlJournalObserver($stream);

        $this->expectException(JournalObservationFailed::class);

        $observer->observe(self::record());
    }

    /**
     * @return resource
     */
    private static function stream(): mixed
    {
        $stream = fopen('php://temp', 'r+b');
        self::assertIsResource($stream);

        return $stream;
    }

    private static function record(): ObservedJournalRecord
    {
        return new ObservedJournalRecord(
            JournalRecordId::fromString(self::ID),
            1,
            JournalEvent::OperationReceived,
            new DateTimeImmutable('2026-07-07T08:00:01.123456', new DateTimeZone('Asia/Tokyo')),
            1,
            new JournalOperation(
                OperationId::fromString(self::ID),
                'dispatch.test',
                1,
                'inline',
                CorrelationId::fromString(self::ID),
                actorContext: new ActorContext(
                    new ActorRef('[masked]', 'user'),
                    null,
                    new ActorRef('[masked]', 'system'),
                ),
            ),
            new JournalAttempt(
                AttemptId::fromString(self::ID),
                1,
                new DateTimeImmutable('2026-07-07T08:00:00.123456', new DateTimeZone('Asia/Tokyo')),
            ),
            ['value' => ['message' => 'hello']],
        );
    }
}
