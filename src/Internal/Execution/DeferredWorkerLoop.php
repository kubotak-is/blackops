<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\Execution\ClaimRequest;
use BlackOps\Core\Execution\ClaimSettlement;
use BlackOps\Core\Execution\OperationReceiver;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;

final readonly class DeferredWorkerLoop implements WorkerLoop
{
    public function __construct(
        private ExpiredAttemptRecovery $recovery,
        private OperationReceiver $receiver,
        private DeferredClaimRuntime $runtime,
        private ClaimSettlement $settlement,
        private WorkerSignalRuntime $signals,
        private ClockInterface $clock,
        private WorkerSleeper $sleeper = new NativeWorkerSleeper(),
        private bool $continueAfterHandlerFailure = true,
    ) {}

    public function run(?int $maximumIterations = null, int $idleSleepMilliseconds = 1_000): int
    {
        if ($maximumIterations !== null && $maximumIterations < 1) {
            throw new InvalidArgumentException('Worker maximum iterations must be positive.');
        }

        if ($idleSleepMilliseconds < 1) {
            throw new InvalidArgumentException('Worker idle sleep must be positive.');
        }

        return $this->signals->runLoop(fn(): int => $this->iterate($maximumIterations, $idleSleepMilliseconds));
    }

    private function iterate(?int $maximumIterations, int $idleSleepMilliseconds): int
    {
        $iteration = 0;
        $processed = 0;

        while (($maximumIterations === null || $iteration < $maximumIterations) && !$this->signals->stopRequested()) {
            ++$iteration;
            $this->recovery->recoverOne($this->clock->now());

            if ($this->signals->stopRequested()) {
                break;
            }

            $claim = $this->receiver->claim(new ClaimRequest($this->clock->now()));

            if ($claim === null) {
                $this->sleeper->sleepMilliseconds($idleSleepMilliseconds);
                continue;
            }

            try {
                $this->runtime->run($claim);
            } catch (SupervisedHandlerFailureException $exception) {
                if (!$this->continueAfterHandlerFailure) {
                    throw $exception;
                }

                continue;
            }

            $this->settlement->acknowledge($claim);
            ++$processed;
        }

        return $processed;
    }
}
