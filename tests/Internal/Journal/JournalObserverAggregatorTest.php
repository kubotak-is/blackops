<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Journal;

use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\JournalRecordId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Internal\Journal\JournalObserverAggregator;
use BlackOps\Internal\Journal\JournalObserverBinding;
use BlackOps\Journal\Exception\JournalObservationFailed;
use BlackOps\Journal\FlushableJournalObserver;
use BlackOps\Journal\JournalDeliveryPolicy;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalObserver;
use BlackOps\Journal\JournalOperation;
use BlackOps\Journal\ObservedJournalRecord;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class JournalObserverAggregatorTest extends TestCase
{
    private const ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';

    public function testBestEffortFailureIsAggregatedWithoutThrowing(): void
    {
        $success = new RecordingObserver();
        $aggregator = new JournalObserverAggregator([
            new JournalObserverBinding('failing', new FailingObserver()),
            new JournalObserverBinding('success', $success),
        ]);

        $result = $aggregator->observe(self::record());

        self::assertTrue($result->hasFailures());
        self::assertSame(['success'], $result->successfulObservers());
        self::assertSame('failing', $result->failures()[0]->observerName());
        self::assertSame(1, $success->observed);
    }

    public function testRequiredFailureThrowsAfterTryingRemainingObservers(): void
    {
        $success = new RecordingObserver();
        $aggregator = new JournalObserverAggregator([
            new JournalObserverBinding('required', new FailingObserver(), JournalDeliveryPolicy::Required),
            new JournalObserverBinding('success', $success),
        ]);

        try {
            $aggregator->observe(self::record());
            self::fail('Required observer failure must block delivery.');
        } catch (JournalObservationFailed) {
            self::assertSame(1, $success->observed);
        }
    }

    public function testDurableFailureBlocksDelivery(): void
    {
        $aggregator = new JournalObserverAggregator([
            new JournalObserverBinding('durable', new FailingObserver(), JournalDeliveryPolicy::Durable),
        ]);

        $this->expectException(JournalObservationFailed::class);

        $aggregator->observe(self::record());
    }

    public function testFlushOnlyFlushableObservers(): void
    {
        $flushable = new RecordingFlushableObserver();
        $plain = new RecordingObserver();
        $aggregator = new JournalObserverAggregator([
            new JournalObserverBinding('flushable', $flushable),
            new JournalObserverBinding('plain', $plain),
        ]);

        $result = $aggregator->flush();

        self::assertSame(['flushable'], $result->successfulObservers());
        self::assertSame(1, $flushable->flushed);
        self::assertSame(0, $plain->observed);
    }

    private static function record(): ObservedJournalRecord
    {
        return new ObservedJournalRecord(
            JournalRecordId::fromString(self::ID),
            1,
            JournalEvent::OperationReceived,
            new DateTimeImmutable('2026-07-07T00:00:00Z'),
            1,
            new JournalOperation(
                OperationId::fromString(self::ID),
                'observer.test',
                1,
                'inline',
                CorrelationId::fromString(self::ID),
            ),
            null,
            ['value' => ['message' => 'hello']],
        );
    }
}

final class RecordingObserver implements JournalObserver
{
    public int $observed = 0;

    public function observe(ObservedJournalRecord $record): void
    {
        $this->observed++;
    }
}

final class RecordingFlushableObserver implements FlushableJournalObserver
{
    public int $observed = 0;

    public int $flushed = 0;

    public function observe(ObservedJournalRecord $record): void
    {
        $this->observed++;
    }

    public function flush(): void
    {
        $this->flushed++;
    }
}

final class FailingObserver implements JournalObserver
{
    public function observe(ObservedJournalRecord $record): void
    {
        throw new JournalObservationFailed('observer unavailable');
    }
}
