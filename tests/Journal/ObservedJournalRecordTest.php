<?php

declare(strict_types=1);

namespace BlackOps\Tests\Journal;

use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\ObservedJournalRecord;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ObservedJournalRecordTest extends TestCase
{
    private const ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';

    public function testBuildsSafeRecordAndNormalizesTime(): void
    {
        $record = new ObservedJournalRecord(
            JournalRecordId::fromString(self::ID),
            1,
            JournalEvent::OperationReceived,
            new DateTimeImmutable('2026-07-07T08:00:01.123456', new DateTimeZone('Asia/Tokyo')),
            2,
            new JournalOperation(
                OperationId::fromString(self::ID),
                'welcome.show',
                1,
                'inline',
                CorrelationId::fromString(self::ID),
            ),
            null,
            ['value' => ['message' => 'hello']],
        );

        self::assertSame('2026-07-06T23:00:01.123456Z', $record->occurredAt->format('Y-m-d\TH:i:s.u\Z'));
        self::assertSame(['value' => ['message' => 'hello']], $record->data);
    }

    public function testPublicReadonlyShape(): void
    {
        $reflection = new ReflectionClass(ObservedJournalRecord::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertCount(1, $reflection->getAttributes(PublicApi::class));
    }

    public function testInvalidSequenceIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ObservedJournalRecord(
            JournalRecordId::fromString(self::ID),
            1,
            JournalEvent::OperationReceived,
            new DateTimeImmutable(),
            0,
            new JournalOperation(
                OperationId::fromString(self::ID),
                'welcome.show',
                1,
                'inline',
                CorrelationId::fromString(self::ID),
            ),
            null,
            [],
        );
    }
}
