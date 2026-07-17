<?php

declare(strict_types=1);

namespace BlackOps\Tests\Journal;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalAttempt;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\JournalRecord;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class JournalRecordTest extends TestCase
{
    private const ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';

    public function testBuildsNestedRecordAndNormalizesTimes(): void
    {
        $attempt = new JournalAttempt(
            AttemptId::fromString(self::ID),
            1,
            new DateTimeImmutable('2026-07-07T08:00:00.123456', new DateTimeZone('Asia/Tokyo')),
        );
        $operation = new JournalOperation(
            OperationId::fromString(self::ID),
            'welcome.show',
            1,
            'inline',
            CorrelationId::fromString(self::ID),
        );
        $record = new JournalRecord(
            JournalRecordId::fromString(self::ID),
            1,
            JournalEvent::AttemptStarted,
            new DateTimeImmutable('2026-07-07T08:00:01.123456', new DateTimeZone('Asia/Tokyo')),
            2,
            $operation,
            $attempt,
            new EmptyJournalData(),
        );

        self::assertSame('2026-07-06T23:00:00.123456Z', $attempt->startedAt->format('Y-m-d\TH:i:s.u\Z'));
        self::assertSame('2026-07-06T23:00:01.123456Z', $record->occurredAt->format('Y-m-d\TH:i:s.u\Z'));
        self::assertSame($operation, $record->operation);
        self::assertSame($attempt, $record->attempt);
    }

    public function testPublicReadonlyShapes(): void
    {
        foreach ([JournalRecord::class, JournalOperation::class, JournalAttempt::class] as $type) {
            $reflection = new ReflectionClass($type);
            self::assertTrue($reflection->isFinal());
            self::assertTrue($reflection->isReadOnly());
            self::assertCount(1, $reflection->getAttributes(PublicApi::class));
        }
    }

    public function testJournalOperationAcceptsOptionalActorContextWithoutBreakingLegacyConstruction(): void
    {
        $legacy = new JournalOperation(
            OperationId::fromString(self::ID),
            'welcome.show',
            1,
            'inline',
            CorrelationId::fromString(self::ID),
        );
        $actors = new ActorContext(
            new ActorRef('user-123', 'user'),
            new ActorRef('user-123', 'user'),
            new ActorRef('http-runtime', 'system'),
        );
        $withActors = new JournalOperation(
            OperationId::fromString(self::ID),
            'welcome.show',
            1,
            'inline',
            CorrelationId::fromString(self::ID),
            actorContext: $actors,
        );

        self::assertNull($legacy->actorContext);
        self::assertSame($actors, $withActors->actorContext);
    }

    public function testInvalidSequenceIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new JournalRecord(
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
            new EmptyJournalData(),
        );
    }
}
