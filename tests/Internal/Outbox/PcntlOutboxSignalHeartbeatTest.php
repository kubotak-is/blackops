<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Outbox;

use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Identifier\OutboxRecordId;
use BlackOps\Internal\Execution\WorkerClaimLostException;
use BlackOps\Internal\Execution\WorkerGracePeriodExceededException;
use BlackOps\Internal\Outbox\PcntlOutboxSignalHeartbeat;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxClaim;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PcntlOutboxSignalHeartbeatTest extends TestCase
{
    public function testHeartbeatRunsDuringDeliveryAndSignalStateIsRestored(): void
    {
        if (!function_exists('posix_kill')) {
            self::markTestSkipped('POSIX signal sending is unavailable.');
        }

        $calls = 0;
        $signals = new PcntlOutboxSignalHeartbeat(
            static function (PostgreSqlOutboxClaim $claim) use (&$calls): void {
                ++$calls;
            },
            30,
            60,
            2,
        );
        $previousAsync = pcntl_async_signals();
        $previousAlarm = pcntl_signal_get_handler(SIGALRM);
        $previousTerm = pcntl_signal_get_handler(SIGTERM);
        $previousInt = pcntl_signal_get_handler(SIGINT);

        $signals->runLoop(function () use ($signals): void {
            $signals->run(self::claim(), static function (): void {
                if (!posix_kill(getmypid(), SIGALRM)) {
                    throw new RuntimeException('SIGALRM could not be sent to the test process.');
                }
                pcntl_signal_dispatch();
            });
        });

        self::assertSame(1, $calls);
        self::assertSame($previousAsync, pcntl_async_signals());
        self::assertSame($previousAlarm, pcntl_signal_get_handler(SIGALRM));
        self::assertSame($previousTerm, pcntl_signal_get_handler(SIGTERM));
        self::assertSame($previousInt, pcntl_signal_get_handler(SIGINT));
    }

    public function testGraceDeadlineInterruptsBlockingDeliveryAndTheInstanceCanBeReused(): void
    {
        if (!function_exists('posix_kill')) {
            self::markTestSkipped('POSIX signal sending is unavailable.');
        }

        $signals = new PcntlOutboxSignalHeartbeat(static function (PostgreSqlOutboxClaim $claim): void {}, 1, 3, 1);
        $this->expectException(WorkerGracePeriodExceededException::class);

        try {
            $signals->runLoop(function () use ($signals): void {
                $signals->run(self::claim(), static function () use ($signals): never {
                    $signals->requestStop();
                    usleep(1_100_000);
                    throw new RuntimeException('Grace period alarm was not dispatched.');
                });
            });
        } finally {
            $stopped = $signals->runLoop(static function () use ($signals): bool {
                posix_kill(getmypid(), SIGINT);

                return $signals->stopRequested();
            });
            self::assertTrue($stopped);
            self::assertFalse($signals->runLoop($signals->stopRequested(...)));
        }
    }

    public function testHeartbeatFailureInterruptsDeliveryAsLeaseLoss(): void
    {
        if (!function_exists('posix_kill')) {
            self::markTestSkipped('POSIX signal sending is unavailable.');
        }

        $signals = new PcntlOutboxSignalHeartbeat(
            static function (PostgreSqlOutboxClaim $claim): void {
                throw new RuntimeException('database connection failed');
            },
            30,
            60,
            2,
        );
        $this->expectException(WorkerClaimLostException::class);

        $signals->runLoop(function () use ($signals): void {
            $signals->run(self::claim(), static function (): void {
                if (!posix_kill(getmypid(), SIGALRM)) {
                    throw new RuntimeException('SIGALRM could not be sent to the test process.');
                }
                pcntl_signal_dispatch();
            });
        });
    }

    /** @return iterable<string, array{int}> */
    public static function terminationSignals(): iterable
    {
        yield 'SIGTERM' => [SIGTERM];
        yield 'SIGINT' => [SIGINT];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('terminationSignals')]
    public function testTerminationSignalsRequestStop(int $signal): void
    {
        if (!function_exists('posix_kill')) {
            self::markTestSkipped('POSIX signal sending is unavailable.');
        }

        $signals = new PcntlOutboxSignalHeartbeat(static function (PostgreSqlOutboxClaim $claim): void {}, 1, 3, 1);
        self::assertTrue($signals->runLoop(static function () use ($signals, $signal): bool {
            posix_kill(getmypid(), $signal);

            return $signals->stopRequested();
        }));
    }

    private static function claim(): PostgreSqlOutboxClaim
    {
        $id = '019f45b2-7c2d-7abc-8def-0123456789ab';

        return new PostgreSqlOutboxClaim(
            OutboxRecordId::fromString($id),
            new DeferredOperationMessage(
                OperationId::fromString('019f45b2-7c2d-7abc-8def-0123456789ac'),
                'mail.send',
                1,
                '{}',
                '{}',
                new DateTimeImmutable('2026-07-24T00:00:00Z'),
            ),
            'relay-test',
            1,
            1,
            new DateTimeImmutable('2026-07-24T00:01:00Z'),
        );
    }
}
