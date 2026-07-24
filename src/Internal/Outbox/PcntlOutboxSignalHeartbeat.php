<?php

declare(strict_types=1);

namespace BlackOps\Internal\Outbox;

use BlackOps\Internal\Execution\PcntlSignalSupport;
use BlackOps\Internal\Execution\WorkerClaimLostException;
use BlackOps\Internal\Execution\WorkerGracePeriodExceededException;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxClaim;
use Closure;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * Supervises one synchronous outbox delivery while refreshing its lease.
 *
 * The heartbeat callback must use a connection independent from the delivery
 * connection so a blocked transport call cannot prevent the lease update.
 */
/** @mago-expect lint:cyclomatic-complexity */
final class PcntlOutboxSignalHeartbeat
{
    private bool $stopRequested = false;

    private bool $loopActive = false;

    private ?PostgreSqlOutboxClaim $activeClaim = null;

    private ?float $graceDeadline = null;

    private Closure $availability;

    /**
     * @param Closure(PostgreSqlOutboxClaim): void $heartbeat
     */
    public function __construct(
        private readonly Closure $heartbeat,
        private readonly int $heartbeatSeconds,
        int $leaseSeconds,
        private readonly int $graceSeconds,
        ?Closure $availability = null,
    ) {
        if ($heartbeatSeconds < 1 || $leaseSeconds < 1 || $heartbeatSeconds >= $leaseSeconds) {
            throw new InvalidArgumentException('Outbox heartbeat interval must be positive and shorter than lease.');
        }

        if ($graceSeconds < 1) {
            throw new InvalidArgumentException('Outbox grace period must be positive.');
        }

        $this->availability = $availability ?? PcntlSignalSupport::available(...);
        $this->assertAvailable();
    }

    /**
     * @template TResult
     *
     * @param Closure(): TResult $loop
     *
     * @return TResult
     */
    public function runLoop(Closure $loop): mixed
    {
        $this->assertAvailable();

        if ($this->loopActive) {
            return $loop();
        }

        $previousAsync = pcntl_async_signals();
        $previousAlarm = pcntl_alarm(0);
        $previousAlarmHandler = PcntlSignalSupport::handler(SIGALRM);
        $previousTermHandler = PcntlSignalSupport::handler(SIGTERM);
        $previousIntHandler = PcntlSignalSupport::handler(SIGINT);
        $this->stopRequested = false;
        $this->graceDeadline = null;
        $this->loopActive = true;

        pcntl_async_signals(true);
        pcntl_signal(SIGALRM, $this->handleAlarm(...));
        pcntl_signal(SIGTERM, $this->requestStop(...));
        pcntl_signal(SIGINT, $this->requestStop(...));

        try {
            return $loop();
        } finally {
            $this->loopActive = false;
            pcntl_alarm(0);
            $this->activeClaim = null;
            $this->graceDeadline = null;
            pcntl_signal(SIGALRM, $previousAlarmHandler);
            pcntl_signal(SIGTERM, $previousTermHandler);
            pcntl_signal(SIGINT, $previousIntHandler);
            pcntl_async_signals($previousAsync);

            if ($previousAlarm > 0) {
                pcntl_alarm($previousAlarm);
            }
        }
    }

    /**
     * @template TResult
     *
     * @param Closure(): TResult $operation
     *
     * @return TResult
     */
    public function run(PostgreSqlOutboxClaim $claim, Closure $operation): mixed
    {
        if (!$this->loopActive) {
            throw new LogicException('Outbox claim execution requires an active signal loop.');
        }

        $this->activeClaim = $claim;
        $this->armAlarm();

        try {
            return $operation();
        } finally {
            pcntl_alarm(0);
            $this->activeClaim = null;
            if (!$this->stopRequested) {
                $this->graceDeadline = null;
            }
        }
    }

    public function stopRequested(): bool
    {
        return $this->stopRequested;
    }

    public function requestStop(): void
    {
        $this->stopRequested = true;

        if ($this->activeClaim !== null && $this->graceDeadline === null) {
            $this->graceDeadline = microtime(true) + $this->graceSeconds;
            $this->armAlarm();
        }
    }

    public function loopActive(): bool
    {
        return $this->loopActive;
    }

    private function handleAlarm(): void
    {
        if ($this->activeClaim === null) {
            return;
        }

        if ($this->graceDeadline !== null && microtime(true) >= $this->graceDeadline) {
            throw new WorkerGracePeriodExceededException('Outbox relay grace period exceeded.');
        }

        try {
            ($this->heartbeat)($this->activeClaim);
        } catch (Throwable $exception) {
            throw new WorkerClaimLostException(
                'Outbox relay heartbeat failed and claim was lost.',
                previous: $exception,
            );
        }

        $this->armAlarm();
    }

    private function armAlarm(): void
    {
        $seconds = $this->heartbeatSeconds;

        if ($this->graceDeadline !== null) {
            $remaining = max(1, (int) ceil($this->graceDeadline - microtime(true)));
            $seconds = min($seconds, $remaining);
        }

        pcntl_alarm($seconds);
    }

    private function assertAvailable(): void
    {
        if (!($this->availability)()) {
            throw new RuntimeException('PCNTL signal functions are required for outbox relay heartbeat runtime.');
        }
    }
}
