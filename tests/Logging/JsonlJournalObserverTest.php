<?php

declare(strict_types=1);

namespace BlackOps\Tests\Logging;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Journal\Exception\JournalObservationFailed;
use BlackOps\Journal\JournalAttempt;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\ObservedJournalRecord;
use BlackOps\Logging\JsonlJournalObserver;
use BlackOps\Logging\JsonlJournalRecordEncoder;
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
