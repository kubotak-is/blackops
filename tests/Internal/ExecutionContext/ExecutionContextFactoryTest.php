<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\ExecutionContext;

use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\AttemptId;
use BlackOps\Core\Identifier\CausationId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Internal\Identifier\SymfonyUuidv7Generator;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class ExecutionContextFactoryTest extends TestCase
{
    public function testReceiveProducesRootContextWithCorrelationIdFromOperationId(): void
    {
        $context = $this->factory()->receive();

        self::assertInstanceOf(OperationId::class, $context->operationId());
        self::assertInstanceOf(CorrelationId::class, $context->correlationId());
        self::assertSame(
            $context->operationId()->toString(),
            $context->correlationId()->toString(),
            'Root Context correlation id must equal the freshly minted operation id UUID value.',
        );
        self::assertNull($context->causationId());
        self::assertNull($context->attempt());
        self::assertNull($context->deadline());
        self::assertSame(
            '2026-07-02T12:34:56.123456Z',
            $context->receivedAt()->format('Y-m-d\TH:i:s.u\Z'),
            'receive() must use the injected clock for receivedAt.',
        );
        self::assertSame('UTC', $context->receivedAt()->getTimezone()->getName());
    }

    public function testReceivePreservesProvidedDeadline(): void
    {
        $deadline = new DateTimeImmutable('2026-07-03T00:00:00.000000', new DateTimeZone('UTC'));

        $context = $this->factory()->receive($deadline);

        self::assertSame('UTC', $context->deadline()->getTimezone()->getName());
        self::assertSame('2026-07-03T00:00:00.000000Z', $context->deadline()->format('Y-m-d\TH:i:s.u\Z'));
    }

    public function testReceiveNormalizesDeadlineToUtc(): void
    {
        $tokyoDeadline = new DateTimeImmutable('2026-07-03T09:00:00.000000', new DateTimeZone('Asia/Tokyo'));

        $context = $this->factory()->receive($tokyoDeadline);

        self::assertSame('UTC', $context->deadline()->getTimezone()->getName());
        self::assertSame('2026-07-03T00:00:00.000000Z', $context->deadline()->format('Y-m-d\TH:i:s.u\Z'));
    }

    public function testStartAttemptProducesAttemptContextAndPreservesOtherFields(): void
    {
        $factory = $this->factoryWithClock([
            '2026-07-02T12:34:56.123456Z',
            '2026-07-02T12:35:10.000000Z',
        ]);

        $context = $factory->receive(null);
        $attempted = $factory->startAttempt($context, 1);

        self::assertSame($context->operationId(), $attempted->operationId());
        self::assertSame($context->receivedAt(), $attempted->receivedAt());
        self::assertSame($context->correlationId(), $attempted->correlationId());
        self::assertSame($context->causationId(), $attempted->causationId());

        $attempt = $attempted->attempt();
        self::assertNotNull($attempt, 'startAttempt() must produce a non-null AttemptContext.');
        self::assertInstanceOf(AttemptId::class, $attempt->id());
        self::assertSame(1, $attempt->number());
        self::assertSame('UTC', $attempt->startedAt()->getTimezone()->getName());
        self::assertSame('2026-07-02T12:35:10.000000Z', $attempt->startedAt()->format('Y-m-d\TH:i:s.u\Z'));
    }

    public function testStartAttemptAcceptsRetryAttemptNumber(): void
    {
        $context = $this->factory()->receive(null);

        $attempted = $this->factory()->startAttempt($context, 3);

        self::assertSame(3, $attempted->attempt()->number());
    }

    public function testStartAttemptRejectsAttemptNumberBelowOne(): void
    {
        $context = $this->factory()->receive(null);

        $this->expectException(InvalidArgumentException::class);

        $this->factory()->startAttempt($context, 0);
    }

    public function testStartAttemptRejectsAttemptAfterDeadlineReachedAtExactDeadline(): void
    {
        $deadline = new DateTimeImmutable('2026-07-02T12:34:56.000000Z', new DateTimeZone('UTC'));

        $factory = $this->factoryWithClock([
            '2026-07-02T12:00:00.000000Z',
            $deadline->format('Y-m-d\TH:i:s.u\Z'),
        ]);

        $context = $factory->receive($deadline);

        $this->expectException(LogicException::class);

        $factory->startAttempt($context, 1);
    }

    public function testStartAttemptRejectsAttemptAfterDeadlinePassed(): void
    {
        $deadline = new DateTimeImmutable('2026-07-02T12:34:56.000000Z', new DateTimeZone('UTC'));

        $factory = $this->factoryWithClock([
            '2026-07-02T12:00:00.000000Z',
            '2026-07-02T13:00:00.000000Z',
        ]);

        $context = $factory->receive($deadline);

        $this->expectException(LogicException::class);

        $factory->startAttempt($context, 1);
    }

    public function testStartAttemptAllowsAttemptExactlyBeforeDeadline(): void
    {
        $deadline = new DateTimeImmutable('2026-07-02T12:34:56.000000Z', new DateTimeZone('UTC'));

        $factory = $this->factoryWithClock([
            '2026-07-02T12:00:00.000000Z',
            '2026-07-02T12:34:55.999999Z',
        ]);

        $context = $factory->receive($deadline);
        $attempted = $factory->startAttempt($context, 1);

        self::assertNotNull($attempted->attempt());
    }

    public function testStartAttemptOnContextWithoutDeadlineIsAlwaysAllowed(): void
    {
        $context = $this->factory()->receive(null);

        $attempted = $this->factory()->startAttempt($context, 1);

        self::assertNotNull($attempted->attempt());
    }

    public function testCreateChildProducesNewContextPropagatingCorrelationAndCausation(): void
    {
        $factory = $this->factoryWithClock([
            '2026-07-02T12:34:56.123456Z',
            '2026-07-02T12:40:00.000000Z',
        ]);

        $parent = $factory->receive(null);
        $child = $factory->createChild($parent);

        self::assertNotSame(
            $parent->operationId()->toString(),
            $child->operationId()->toString(),
            'Child Context must have a fresh OperationId.',
        );
        self::assertInstanceOf(OperationId::class, $child->operationId());

        self::assertSame(
            $parent->correlationId(),
            $child->correlationId(),
            'Child Context must propagate the parent correlation id.',
        );

        self::assertNotNull($child->causationId());
        self::assertInstanceOf(CausationId::class, $child->causationId());
        self::assertSame(
            $parent->operationId()->toString(),
            $child->causationId()->toString(),
            'Child CausationId must equal the parent OperationId UUID value.',
        );

        self::assertNull($child->attempt(), 'Child Context must not carry an attempt.');
        self::assertSame('2026-07-02T12:40:00.000000Z', $child->receivedAt()->format('Y-m-d\TH:i:s.u\Z'));
        self::assertSame('UTC', $child->receivedAt()->getTimezone()->getName());
    }

    public function testCreateChildInheritsParentDeadlineWhenOmitted(): void
    {
        $deadline = new DateTimeImmutable('2026-07-03T00:00:00.000000', new DateTimeZone('UTC'));

        $factory = $this->factoryWithClock([
            '2026-07-02T12:34:56.123456Z',
            '2026-07-02T12:40:00.000000Z',
        ]);

        $parent = $factory->receive($deadline);
        $child = $factory->createChild($parent);

        self::assertSame(
            $parent->deadline()->format('Y-m-d\TH:i:s.u\Z'),
            $child->deadline()->format('Y-m-d\TH:i:s.u\Z'),
            'Child must inherit parent deadline when omitted.',
        );
    }

    public function testCreateChildWithoutParentDeadlineAndOmittedChildDeadlineReturnsNullDeadline(): void
    {
        $factory = $this->factoryWithClock([
            '2026-07-02T12:34:56.123456Z',
            '2026-07-02T12:40:00.000000Z',
        ]);

        $parent = $factory->receive(null);
        $child = $factory->createChild($parent);

        self::assertNull($parent->deadline());
        self::assertNull($child->deadline());
    }

    public function testCreateChildAcceptsEarlierChildDeadline(): void
    {
        $parentDeadline = new DateTimeImmutable('2026-07-03T00:00:00.000000', new DateTimeZone('UTC'));
        $childDeadline = new DateTimeImmutable('2026-07-02T20:00:00.000000', new DateTimeZone('UTC'));

        $factory = $this->factoryWithClock([
            '2026-07-02T12:34:56.123456Z',
            '2026-07-02T12:40:00.000000Z',
        ]);

        $parent = $factory->receive($parentDeadline);
        $child = $factory->createChild($parent, $childDeadline);

        self::assertSame('2026-07-02T20:00:00.000000Z', $child->deadline()->format('Y-m-d\TH:i:s.u\Z'));
    }

    public function testCreateChildAcceptsChildDeadlineEqualParentDeadline(): void
    {
        $deadline = new DateTimeImmutable('2026-07-03T00:00:00.000000', new DateTimeZone('UTC'));

        $factory = $this->factoryWithClock([
            '2026-07-02T12:34:56.123456Z',
            '2026-07-02T12:40:00.000000Z',
        ]);

        $parent = $factory->receive($deadline);
        $child = $factory->createChild($parent, $deadline);

        self::assertSame('2026-07-03T00:00:00.000000Z', $child->deadline()->format('Y-m-d\TH:i:s.u\Z'));
    }

    public function testCreateChildRejectsDeadlineLaterThanParent(): void
    {
        $parentDeadline = new DateTimeImmutable('2026-07-03T00:00:00.000000', new DateTimeZone('UTC'));
        $laterChildDeadline = new DateTimeImmutable('2026-07-04T00:00:00.000000', new DateTimeZone('UTC'));

        $factory = $this->factoryWithClock([
            '2026-07-02T12:34:56.123456Z',
            '2026-07-02T12:40:00.000000Z',
        ]);

        $parent = $factory->receive($parentDeadline);

        $this->expectException(InvalidArgumentException::class);

        $factory->createChild($parent, $laterChildDeadline);
    }

    public function testCreateChildAllowsChildDeadlineWhenParentHasNoDeadline(): void
    {
        $childDeadline = new DateTimeImmutable('2026-07-04T00:00:00.000000', new DateTimeZone('UTC'));

        $factory = $this->factoryWithClock([
            '2026-07-02T12:34:56.123456Z',
            '2026-07-02T12:40:00.000000Z',
        ]);

        $parent = $factory->receive(null);
        $child = $factory->createChild($parent, $childDeadline);

        self::assertSame('2026-07-04T00:00:00.000000Z', $child->deadline()->format('Y-m-d\TH:i:s.u\Z'));
    }

    private function factory(): ExecutionContextFactory
    {
        return $this->factoryWithClock(null);
    }

    /**
     * @param list<string>|null $ecClockTimes Sequence of UTC timestamps consumed by the ExecutionContextFactory clock.
     */
    private function factoryWithClock(?array $ecClockTimes): ExecutionContextFactory
    {
        $identifierClock = $this->fixedClock('2026-07-01T00:00:00.000000Z');

        return new ExecutionContextFactory(
            new IdentifierFactory(new SymfonyUuidv7Generator(), $identifierClock),
            $ecClockTimes === null
                ? $this->fixedClock('2026-07-02T12:34:56.123456Z')
                : $this->sequenceClock($ecClockTimes),
        );
    }

    private function fixedClock(string $time): ClockInterface
    {
        $now = new DateTimeImmutable($time, new DateTimeZone('UTC'));

        return new readonly class($now) implements ClockInterface {
            public function __construct(
                private readonly DateTimeImmutable $now,
            ) {}

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        };
    }

    /**
     * @param list<string> $times ISO 8601 UTC strings; consumed in order.
     */
    private function sequenceClock(array $times): ClockInterface
    {
        return new class($times) implements ClockInterface {
            private int $index = 0;

            /**
             * @param list<string> $times
             */
            public function __construct(
                private readonly array $times,
            ) {}

            public function now(): DateTimeImmutable
            {
                if (!isset($this->times[$this->index])) {
                    throw new LogicException('SequenceClock has no more queued timestamps.');
                }

                return new DateTimeImmutable($this->times[$this->index++], new DateTimeZone('UTC'));
            }
        };
    }
}
