<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Execution;

use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\Execution\Inline;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationHandler;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Internal\Execution\HandlerResolver;
use BlackOps\Internal\Execution\InlineDispatcher;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\Uuidv7Generator;
use BlackOps\Internal\Journal\JournalRecordFactory;
use BlackOps\Internal\Journal\LifecycleStateMachine;
use BlackOps\Journal\CanonicalJournalWriter;
use BlackOps\Journal\Exception\JournalWriteFailed;
use BlackOps\Journal\Exception\LifecycleTransitionException;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\JournalRecord;
use BlackOps\Journal\LifecycleState;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;

final class InlineDispatcherTest extends TestCase
{
    public function testDispatchBuildsAttemptEnvelopeAndReturnsHandlerResult(): void
    {
        $journal = new RecordingJournalWriter();
        $result = $this->dispatcher(new DispatchHandler(), $journal)->dispatch(
            new DispatchOperation(),
            new DispatchValue('hello'),
        );

        self::assertTrue($result->isCompleted());
        self::assertInstanceOf(EmptyOutcome::class, $result->outcome());
        self::assertSame(
            [
                JournalEvent::OperationReceived,
                JournalEvent::AttemptStarted,
                JournalEvent::AttemptSucceeded,
                JournalEvent::OperationCompleted,
            ],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $journal->records),
        );
        self::assertSame([1, 2, 3, 4], array_column($journal->records, 'sequence'));
    }

    public function testMismatchedValueIsRejected(): void
    {
        $this->expectException(LogicException::class);
        $this->dispatcher(new DispatchHandler())->dispatch(new DispatchOperation(), new OtherDispatchValue());
    }

    public function testHandlerExceptionPropagates(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->dispatcher(new ThrowingDispatchHandler())->dispatch(new DispatchOperation(), new DispatchValue('hello'));
    }

    public function testRejectedResultWritesTerminalRejectedEvent(): void
    {
        $journal = new RecordingJournalWriter();
        $result = $this->dispatcher(new RejectingDispatchHandler(), $journal)->dispatch(
            new DispatchOperation(),
            new DispatchValue('hello'),
        );

        self::assertTrue($result->isRejected());
        self::assertSame(
            [JournalEvent::OperationReceived, JournalEvent::AttemptStarted, JournalEvent::OperationRejected],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $journal->records),
        );
        self::assertSame([1, 2, 3], array_column($journal->records, 'sequence'));
    }

    public function testJournalWriterFailurePropagates(): void
    {
        $this->expectException(JournalWriteFailed::class);

        $this->dispatcher(new DispatchHandler(), new FailingJournalWriter())->dispatch(
            new DispatchOperation(),
            new DispatchValue('hello'),
        );
    }

    public function testInvalidLifecycleTransitionPreventsTerminalRecordAppend(): void
    {
        $journal = new RecordingJournalWriter();

        try {
            $this->dispatcher(new DispatchHandler(), $journal, new InvalidFinalizingStateMachine())->dispatch(
                new DispatchOperation(),
                new DispatchValue('hello'),
            );
            self::fail('Expected lifecycle transition failure.');
        } catch (LifecycleTransitionException) {
        }

        self::assertSame(
            [JournalEvent::OperationReceived, JournalEvent::AttemptStarted, JournalEvent::AttemptSucceeded],
            array_map(static fn(JournalRecord $record): JournalEvent => $record->event, $journal->records),
        );
    }

    private function dispatcher(
        OperationHandler $handler,
        ?CanonicalJournalWriter $journal = null,
        ?LifecycleStateMachine $lifecycle = null,
    ): InlineDispatcher {
        $metadata = new OperationMetadata(
            'dispatch.test',
            DispatchOperation::class,
            DispatchValue::class,
            $handler::class,
            EmptyOutcome::class,
            Inline::class,
        );
        $container = new class($handler) implements ContainerInterface {
            public function __construct(
                private readonly object $service,
            ) {}

            public function get(string $id): mixed
            {
                return $this->service;
            }

            public function has(string $id): bool
            {
                return true;
            }
        };
        $clock = new class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-07-06T00:00:00.000000Z');
            }
        };
        $generator = new class implements Uuidv7Generator {
            public function generate(DateTimeImmutable $time): string
            {
                return '019f32ab-2be0-7b38-a0a7-1ab2f9687697';
            }
        };
        $identifiers = new IdentifierFactory($generator, $clock);

        return new InlineDispatcher(
            new OperationRegistry([$metadata]),
            new ExecutionContextFactory($identifiers, $clock),
            new HandlerResolver($container),
            new JournalRecordFactory($identifiers, $clock),
            $journal ?? new RecordingJournalWriter(),
            $lifecycle ?? new LifecycleStateMachine(),
        );
    }
}

final readonly class DispatchOperation implements Operation {}

final readonly class DispatchValue implements OperationValue
{
    public function __construct(
        public string $message,
    ) {}
}

final readonly class OtherDispatchValue implements OperationValue {}

/** @implements OperationHandler<DispatchValue, EmptyOutcome> */
final readonly class DispatchHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        if ($operation->context()->attempt() === null) {
            throw new LogicException('Attempt is required.');
        }
        return OperationResult::completed();
    }
}

/** @implements OperationHandler<DispatchValue, EmptyOutcome> */
final readonly class ThrowingDispatchHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        throw new \RuntimeException('handler failed');
    }
}

/** @implements OperationHandler<DispatchValue, EmptyOutcome> */
final readonly class RejectingDispatchHandler implements OperationHandler
{
    public function handle(OperationEnvelope $operation): OperationResult
    {
        return OperationResult::rejected(RejectionReason::conflict('dispatch_rejected'));
    }
}

final class RecordingJournalWriter implements CanonicalJournalWriter
{
    /** @var list<JournalRecord> */
    public array $records = [];

    public function append(JournalRecord $record): void
    {
        $this->records[] = $record;
    }
}

final readonly class FailingJournalWriter implements CanonicalJournalWriter
{
    public function append(JournalRecord $record): void
    {
        throw new JournalWriteFailed('journal unavailable');
    }
}

final readonly class InvalidFinalizingStateMachine extends LifecycleStateMachine
{
    public function next(?LifecycleState $current, JournalEvent $event): LifecycleState
    {
        if ($event === JournalEvent::OperationCompleted) {
            throw LifecycleTransitionException::invalid($current, $event);
        }

        return parent::next($current, $event);
    }
}
