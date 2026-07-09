<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Journal;

use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Journal\Data\OperationReceivedData;
use BlackOps\Journal\EmptyJournalData;
use BlackOps\Journal\JournalEvent;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class JournalRecordFactoryTest extends TestCase
{
    public const ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';

    public function testCreatesReceivedRecordWithCanonicalValue(): void
    {
        $clock = new class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-07-06T12:00:00.123456Z');
            }
        };
        $generator = new class implements Uuidv7Generator {
            public function generate(DateTimeImmutable $time): string
            {
                return JournalRecordFactoryTest::ID;
            }
        };
        $value = new JournalValueFixture('hello');
        $context = new ExecutionContext(
            OperationId::fromString(self::ID),
            $clock->now(),
            CorrelationId::fromString(self::ID),
        );
        $envelope = new OperationEnvelope(new JournalOperationFixture(), $value, $context, new Inline());
        $metadata = new OperationMetadata(
            'journal.test',
            JournalOperationFixture::class,
            JournalValueFixture::class,
            JournalHandlerFixture::class,
            EmptyOutcome::class,
            Inline::class,
        );

        $record = new JournalRecordFactory(new IdentifierFactory($generator, $clock), $clock)->operationReceived(
            $envelope,
            $metadata,
            1,
        );

        self::assertSame(JournalEvent::OperationReceived, $record->event);
        self::assertSame(1, $record->sequence);
        self::assertInstanceOf(OperationReceivedData::class, $record->data);
        self::assertSame($value, $record->data->value);
        self::assertSame('inline', $record->operation->strategy);
    }

    public function testCreatesAcceptedRecordForDeferredOperation(): void
    {
        $clock = new class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-07-06T12:00:00.123456Z');
            }
        };
        $generator = new class implements Uuidv7Generator {
            public function generate(DateTimeImmutable $time): string
            {
                return JournalRecordFactoryTest::ID;
            }
        };
        $context = new ExecutionContext(
            OperationId::fromString(self::ID),
            $clock->now(),
            CorrelationId::fromString(self::ID),
        );
        $envelope = new OperationEnvelope(
            new JournalOperationFixture(),
            new JournalValueFixture('hello'),
            $context,
            new Deferred(),
        );
        $metadata = new OperationMetadata(
            'journal.test',
            JournalOperationFixture::class,
            JournalValueFixture::class,
            JournalHandlerFixture::class,
            EmptyOutcome::class,
            Deferred::class,
        );

        $record = new JournalRecordFactory(new IdentifierFactory($generator, $clock), $clock)->operationAccepted(
            $envelope,
            $metadata,
            2,
        );

        self::assertSame(JournalEvent::OperationAccepted, $record->event);
        self::assertSame(2, $record->sequence);
        self::assertInstanceOf(EmptyJournalData::class, $record->data);
        self::assertSame('deferred', $record->operation->strategy);
    }
}

final readonly class JournalOperationFixture implements Operation {}

final readonly class JournalValueFixture implements OperationValue
{
    public function __construct(
        public string $message,
    ) {}
}

abstract class JournalHandlerFixture implements OperationHandler {}
