<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\Execution\ClaimHeartbeat;
use BlackOps\Core\Execution\OperationClaim;
use Closure;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;

final class PcntlSignalHeartbeat implements WorkerSignalRuntime, ClaimExecutionGuard
{
    private bool $stopRequested = false;

    private bool $loopActive = false;

    private ?OperationClaim $activeClaim = null;

    private ?float $graceDeadline = null;

    private Closure $availability;

    public function __construct(
        private readonly ClaimHeartbeat $heartbeat,
        private readonly int $heartbeatSeconds,
        int $leaseSeconds,
        private readonly int $graceSeconds,
        ?Closure $availability = null,
    ) {
        if ($heartbeatSeconds < 1 || $leaseSeconds < 1 || $heartbeatSeconds >= $leaseSeconds) {
            throw new InvalidArgumentException('Worker heartbeat interval must be positive and shorter than lease.');
        }

        if ($graceSeconds < 1) {
            throw new InvalidArgumentException('Worker grace period must be positive.');
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
        $previousAsync = pcntl_async_signals();
        $previousAlarm = pcntl_alarm(0);
        $previousAlarmHandler = PcntlSignalSupport::handler(SIGALRM);
        $previousTermHandler = PcntlSignalSupport::handler(SIGTERM);
        $previousIntHandler = PcntlSignalSupport::handler(SIGINT);
        $this->stopRequested = false;

        pcntl_async_signals(true);
        pcntl_signal(SIGALRM, $this->handleAlarm(...));
        pcntl_signal(SIGTERM, $this->requestStop(...));
        pcntl_signal(SIGINT, $this->requestStop(...));
        $this->loopActive = true;

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
    public function run(OperationClaim $claim, Closure $operation): mixed
    {
        if (!$this->loopActive) {
            throw new LogicException('Worker claim execution guard requires an active signal loop.');
        }

        $this->activeClaim = $claim;
        $this->armAlarm();

        try {
            return $operation();
        } finally {
            pcntl_alarm(0);
            $this->activeClaim = null;
            $this->graceDeadline = null;
        }
    }

    public function stopRequested(): bool
    {
        return $this->stopRequested;
    }

    private function requestStop(): void
    {
        $this->stopRequested = true;

        if ($this->activeClaim !== null && $this->graceDeadline === null) {
            $this->graceDeadline = microtime(true) + $this->graceSeconds;
            $this->armAlarm();
        }
    }

    private function handleAlarm(): void
    {
        if ($this->activeClaim === null) {
            return;
        }

        if ($this->graceDeadline !== null && microtime(true) >= $this->graceDeadline) {
            throw new WorkerGracePeriodExceededException('Worker grace period exceeded.');
        }

        try {
            $this->activeClaim = $this->heartbeat->heartbeat($this->activeClaim);
        } catch (Throwable $exception) {
            throw new WorkerClaimLostException('Worker heartbeat failed and claim was lost.', previous: $exception);
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
            throw new RuntimeException('PCNTL signal functions are required for worker heartbeat runtime.');
        }
    }
}
