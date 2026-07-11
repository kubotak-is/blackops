<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Execution;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\ClaimHeartbeat;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Execution\OperationClaim;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Internal\Execution\PcntlSignalHeartbeat;
use BlackOps\Internal\Execution\WorkerClaimLostException;
use BlackOps\Internal\Execution\WorkerGracePeriodExceededException;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SignalHeartbeatTest extends TestCase
{
    public function testSigalrmHeartbeatsDuringSynchronousHandlerAndRestoresSignalState(): void
    {
        $heartbeat = new RecordingSignalHeartbeat();
        $signals = new PcntlSignalHeartbeat($heartbeat, 1, 3, 2);
        $previousAsync = pcntl_async_signals();
        $previousAlarm = pcntl_signal_get_handler(SIGALRM);
        $previousTerm = pcntl_signal_get_handler(SIGTERM);
        $previousInt = pcntl_signal_get_handler(SIGINT);

        $result = $signals->runLoop(fn(): string => $signals->run(self::claim(), static function () use (
            $heartbeat,
        ): string {
            $deadline = microtime(true) + 2.5;

            while ($heartbeat->calls === 0 && microtime(true) < $deadline) {
                usleep(10_000);
            }

            return 'completed';
        }));

        self::assertSame('completed', $result);
        self::assertGreaterThanOrEqual(1, $heartbeat->calls);
        self::assertSame($previousAsync, pcntl_async_signals());
        self::assertSame($previousAlarm, pcntl_signal_get_handler(SIGALRM));
        self::assertSame($previousTerm, pcntl_signal_get_handler(SIGTERM));
        self::assertSame($previousInt, pcntl_signal_get_handler(SIGINT));
    }

    public function testHeartbeatFailureInterruptsHandlerAsClaimLost(): void
    {
        $signals = new PcntlSignalHeartbeat(new RecordingSignalHeartbeat(fail: true), 1, 3, 2);

        $this->expectException(WorkerClaimLostException::class);

        $signals->runLoop(fn(): mixed => $signals->run(self::claim(), static function (): never {
            while (true) {
                usleep(10_000);
            }
        }));
    }

    public function testSigtermGraceExpiryInterruptsWithoutHeartbeatSupervision(): void
    {
        if (!function_exists('posix_kill')) {
            self::markTestSkipped('POSIX signal sending is unavailable.');
        }

        $heartbeat = new RecordingSignalHeartbeat();
        $signals = new PcntlSignalHeartbeat($heartbeat, 2, 4, 1);

        $this->expectException(WorkerGracePeriodExceededException::class);

        try {
            $signals->runLoop(fn(): mixed => $signals->run(self::claim(), static function (): never {
                posix_kill(getmypid(), SIGTERM);

                while (true) {
                    usleep(10_000);
                }
            }));
        } finally {
            self::assertSame(0, $heartbeat->calls);
        }
    }

    public function testMissingPcntlFailsFastAtConfiguration(): void
    {
        $this->expectException(RuntimeException::class);

        new PcntlSignalHeartbeat(new RecordingSignalHeartbeat(), 1, 3, 2, static fn(): bool => false);
    }

    public function testClaimGuardFailsBeforeArmingAlarmOutsideSignalLoop(): void
    {
        $signals = new PcntlSignalHeartbeat(new RecordingSignalHeartbeat(), 1, 3, 2);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('active signal loop');

        $signals->run(self::claim(), static fn(): string => 'unsafe');
    }

    public function testSigintRequestsStopWithoutAnActiveClaim(): void
    {
        if (!function_exists('posix_kill')) {
            self::markTestSkipped('POSIX signal sending is unavailable.');
        }

        $signals = new PcntlSignalHeartbeat(new RecordingSignalHeartbeat(), 1, 3, 2);

        $stopRequested = $signals->runLoop(static function () use ($signals): bool {
            posix_kill(getmypid(), SIGINT);

            return $signals->stopRequested();
        });

        self::assertTrue($stopRequested);
    }

    public function testRejectsInvalidHeartbeatAndGraceConfiguration(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PcntlSignalHeartbeat(new RecordingSignalHeartbeat(), 3, 3, 0);
    }

    private static function claim(): OperationClaim
    {
        $id = '019f32ab-2be0-7b38-a0a7-1ab2f9687810';

        return new OperationClaim(
            new DeferredOperationMessage(
                OperationId::fromString($id),
                'worker.signal',
                1,
                '{}',
                '{}',
                new DateTimeImmutable('2026-07-11T00:00:00Z'),
            ),
            $id . ':1',
        );
    }
}

final class RecordingSignalHeartbeat implements ClaimHeartbeat
{
    public int $calls = 0;

    public function __construct(
        private bool $fail = false,
    ) {}

    public function heartbeat(OperationClaim $claim): OperationClaim
    {
        ++$this->calls;

        if ($this->fail) {
            throw new DeferredTransportException('stale');
        }

        return $claim;
    }
}
