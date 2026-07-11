<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Execution;

use BlackOps\Core\Execution\ClaimRequest;
use BlackOps\Core\Execution\ClaimSettlement;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Execution\OperationClaim;
use BlackOps\Core\Execution\OperationReceiver;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\OperationResult;
use BlackOps\Core\Rejection\RejectionReason;
use BlackOps\Internal\Execution\DeferredClaimRuntime;
use BlackOps\Internal\Execution\DeferredWorkerLoop;
use BlackOps\Internal\Execution\ExpiredAttemptRecovery;
use BlackOps\Internal\Execution\SupervisedHandlerFailureException;
use BlackOps\Internal\Execution\WorkerClaimLostException;
use BlackOps\Internal\Execution\WorkerSignalRuntime;
use BlackOps\Internal\Execution\WorkerSleeper;
use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use RuntimeException;

final class DeferredWorkerLoopTest extends TestCase
{
    public function testRecoversBeforeClaimAndAcknowledgesSuccessfulClaims(): void
    {
        $events = new LoopEventLog();
        $claim = self::claim('019f32ab-2be0-7b38-a0a7-1ab2f9687801');
        $recovery = new LoopRecovery($events);
        $receiver = new LoopReceiver([$claim], $events);
        $runtime = new LoopRuntime([OperationResult::completed()], $events);
        $settlement = new LoopSettlement($events);
        $loop = new DeferredWorkerLoop(
            $recovery,
            $receiver,
            $runtime,
            $settlement,
            new LoopSignals(),
            new LoopClock(),
            new LoopSleeper(),
        );

        $processed = $loop->run(1, 10);

        self::assertSame(1, $processed);
        self::assertSame(['recover', 'claim', 'runtime', 'acknowledge'], $events->events);
        self::assertSame([$claim], $settlement->acknowledged);
        self::assertSame([], $settlement->released);
    }

    public function testSleepsWhenNoClaimAndStopsAtFiniteIterations(): void
    {
        $sleeper = new LoopSleeper();
        $receiver = new LoopReceiver([]);
        $loop = new DeferredWorkerLoop(
            new LoopRecovery(),
            $receiver,
            new LoopRuntime([]),
            new LoopSettlement(),
            new LoopSignals(),
            new LoopClock(),
            $sleeper,
        );

        self::assertSame(0, $loop->run(2, 25));
        self::assertSame(2, $receiver->claims);
        self::assertSame([25, 25], $sleeper->milliseconds);
    }

    public function testAcknowledgesRejectedClaimAsTerminal(): void
    {
        $claim = self::claim('019f32ab-2be0-7b38-a0a7-1ab2f9687801');
        $settlement = new LoopSettlement();
        $loop = new DeferredWorkerLoop(
            new LoopRecovery(),
            new LoopReceiver([$claim]),
            new LoopRuntime([OperationResult::rejected(RejectionReason::businessRule('not_allowed'))]),
            $settlement,
            new LoopSignals(),
            new LoopClock(),
            new LoopSleeper(),
        );

        self::assertSame(1, $loop->run(1));
        self::assertSame([$claim], $settlement->acknowledged);
        self::assertSame([], $settlement->released);
    }

    public function testContinuesAfterSupervisedHandlerFailureWithoutAcknowledgingFailedClaim(): void
    {
        $first = self::claim('019f32ab-2be0-7b38-a0a7-1ab2f9687801');
        $second = self::claim('019f32ab-2be0-7b38-a0a7-1ab2f9687802');
        $settlement = new LoopSettlement();
        $loop = new DeferredWorkerLoop(
            new LoopRecovery(),
            new LoopReceiver([$first, $second]),
            new LoopRuntime([
                new SupervisedHandlerFailureException('supervised', previous: new RuntimeException('handler')),
                OperationResult::completed(),
            ]),
            $settlement,
            new LoopSignals(),
            new LoopClock(),
            new LoopSleeper(),
        );

        self::assertSame(1, $loop->run(2));
        self::assertSame([$second], $settlement->acknowledged);
    }

    public function testInfrastructureFailureStopsEvenWhenHandlerFailureContinuationIsEnabled(): void
    {
        $claim = self::claim('019f32ab-2be0-7b38-a0a7-1ab2f9687801');
        $settlement = new LoopSettlement();
        $loop = new DeferredWorkerLoop(
            new LoopRecovery(),
            new LoopReceiver([$claim]),
            new LoopRuntime([new RuntimeException('database unavailable')]),
            $settlement,
            new LoopSignals(),
            new LoopClock(),
            new LoopSleeper(),
            continueAfterHandlerFailure: true,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('database unavailable');

        try {
            $loop->run(1);
        } finally {
            self::assertSame([], $settlement->acknowledged);
            self::assertSame([], $settlement->released);
        }
    }

    public function testClaimLostStopsWithoutSettlementOrRelease(): void
    {
        $claim = self::claim('019f32ab-2be0-7b38-a0a7-1ab2f9687801');
        $settlement = new LoopSettlement();
        $loop = new DeferredWorkerLoop(
            new LoopRecovery(),
            new LoopReceiver([$claim]),
            new LoopRuntime([new WorkerClaimLostException('lost')]),
            $settlement,
            new LoopSignals(),
            new LoopClock(),
            new LoopSleeper(),
        );

        $this->expectException(WorkerClaimLostException::class);

        try {
            $loop->run(1);
        } finally {
            self::assertSame([], $settlement->acknowledged);
            self::assertSame([], $settlement->released);
        }
    }

    public function testStopSignalAfterCurrentClaimPreventsAnotherClaim(): void
    {
        $signals = new LoopSignals(stopAfterRuntime: true);
        $receiver = new LoopReceiver([
            self::claim('019f32ab-2be0-7b38-a0a7-1ab2f9687801'),
            self::claim('019f32ab-2be0-7b38-a0a7-1ab2f9687802'),
        ]);
        $loop = new DeferredWorkerLoop(
            new LoopRecovery(),
            $receiver,
            new LoopRuntime([OperationResult::completed()], signals: $signals),
            new LoopSettlement(),
            $signals,
            new LoopClock(),
            new LoopSleeper(),
        );

        self::assertSame(1, $loop->run());
        self::assertSame(1, $receiver->claims);
    }

    private static function claim(string $id): OperationClaim
    {
        return new OperationClaim(
            new DeferredOperationMessage(
                OperationId::fromString($id),
                'worker.test',
                1,
                '{}',
                '{}',
                new DateTimeImmutable('2026-07-11T00:00:00Z'),
            ),
            $id . ':1',
        );
    }
}

final class LoopRecovery implements ExpiredAttemptRecovery
{
    public function __construct(
        private ?LoopEventLog $events = null,
    ) {}

    public function recoverOne(DateTimeImmutable $expiredAt): bool
    {
        if ($this->events !== null) {
            $this->events->events[] = 'recover';
        }

        return false;
    }
}

final class LoopReceiver implements OperationReceiver
{
    public int $claims = 0;

    /** @param list<OperationClaim> $claims */
    public function __construct(
        private array $queue,
        private ?LoopEventLog $events = null,
    ) {}

    public function claim(ClaimRequest $request): ?OperationClaim
    {
        ++$this->claims;
        if ($this->events !== null) {
            $this->events->events[] = 'claim';
        }

        return array_shift($this->queue);
    }
}

final class LoopRuntime implements DeferredClaimRuntime
{
    /** @param list<OperationResult|\Throwable> $results */
    public function __construct(
        private array $results,
        private ?LoopEventLog $events = null,
        private ?LoopSignals $signals = null,
    ) {}

    public function run(OperationClaim $claim): OperationResult
    {
        if ($this->events !== null) {
            $this->events->events[] = 'runtime';
        }
        $result = array_shift($this->results);

        if ($result instanceof \Throwable) {
            throw $result;
        }

        if ($this->signals !== null) {
            $this->signals->requestStop();
        }

        return $result ?? OperationResult::completed();
    }
}

final class LoopSettlement implements ClaimSettlement
{
    /** @var list<OperationClaim> */
    public array $acknowledged = [];

    /** @var list<OperationClaim> */
    public array $released = [];

    public function __construct(
        private ?LoopEventLog $events = null,
    ) {}

    public function acknowledge(OperationClaim $claim): void
    {
        if ($this->events !== null) {
            $this->events->events[] = 'acknowledge';
        }
        $this->acknowledged[] = $claim;
    }

    public function release(OperationClaim $claim, DateTimeImmutable $availableAt): void
    {
        $this->released[] = $claim;
    }
}

final class LoopSignals implements WorkerSignalRuntime
{
    private bool $stop = false;

    public function __construct(
        private bool $stopAfterRuntime = false,
    ) {}

    public function runLoop(Closure $loop): mixed
    {
        return $loop();
    }

    public function stopRequested(): bool
    {
        return $this->stop;
    }

    public function requestStop(): void
    {
        if ($this->stopAfterRuntime) {
            $this->stop = true;
        }
    }
}

final class LoopEventLog
{
    /** @var list<string> */
    public array $events = [];
}

final readonly class LoopClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-11T00:00:00Z');
    }
}

final class LoopSleeper implements WorkerSleeper
{
    /** @var list<int> */
    public array $milliseconds = [];

    public function sleepMilliseconds(int $milliseconds): void
    {
        $this->milliseconds[] = $milliseconds;
    }
}
