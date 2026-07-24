<?php

declare(strict_types=1);

namespace BlackOps\Internal\Outbox;

use BlackOps\Core\Execution\OperationSender;
use BlackOps\Transport\PostgreSql\PostgreSqlOutboxStore;
use Closure;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Throwable;

final readonly class OutboxRelayRuntime
{
    public function __construct(
        private PostgreSqlOutboxStore $store,
        private OperationSender $sender,
        private OutboxRelayConfiguration $configuration,
        private ClockInterface $clock,
        private ?PostgreSqlOutboxStore $heartbeatStore = null,
        private ?PcntlOutboxSignalHeartbeat $signals = null,
    ) {}

    public function runBatch(?DateTimeImmutable $now = null): OutboxRelayResult
    {
        $now ??= $this->clock->now();
        $run = fn(): OutboxRelayResult => $this->processBatch($now);

        if ($this->signals === null) {
            return $run();
        }

        return $this->signals->runLoop($run);
    }

    /**
     * Run a daemon loop inside one signal scope so stop and grace handling
     * remains active while the process is sleeping between batches.
     *
     * @template TResult
     *
     * @param Closure(): TResult $loop
     *
     * @return TResult
     */
    public function runSignalLoop(Closure $loop): mixed
    {
        if ($this->signals === null) {
            return $loop();
        }

        return $this->signals->runLoop($loop);
    }

    public function stopRequested(): bool
    {
        return $this->signals?->stopRequested() ?? false;
    }

    /** @mago-expect lint:halstead */
    private function processBatch(DateTimeImmutable $now): OutboxRelayResult
    {
        if ($this->stopRequested()) {
            return new OutboxRelayResult();
        }

        $claims = $this->store->claimBatch(
            $this->configuration->id,
            $this->configuration->batchSize,
            $now,
            $this->configuration->leaseSeconds,
        );
        $result = new OutboxRelayResult(claimed: count($claims));
        foreach ($claims as $claim) {
            try {
                ($this->heartbeatStore ?? $this->store)->heartbeat($claim, $now, $this->configuration->leaseSeconds);
                $deliver = function () use ($claim): void {
                    $this->sender->enqueue($claim->message);
                };
                if ($this->signals === null) {
                    $deliver();
                } else {
                    $this->signals->run($claim, $deliver);
                }
                ($this->heartbeatStore ?? $this->store)->heartbeat(
                    $claim,
                    $this->clock->now(),
                    $this->configuration->leaseSeconds,
                );
                $this->store->markSent($claim);
                ++$result->sent;
            } catch (Throwable $exception) {
                $fingerprint = $this->fingerprint($exception);
                try {
                    if ($claim->attemptCount >= $this->configuration->maxAttempts) {
                        $this->store->moveToDeadLetter($claim, $fingerprint);
                        ++$result->deadLettered;
                    } else {
                        $delay = $this->backoffSeconds($claim->attemptCount);
                        $this->store->scheduleRetry(
                            $claim,
                            $this->clock->now()->modify('+' . $delay . ' seconds'),
                            $fingerprint,
                        );
                        ++$result->retried;
                    }
                } catch (Throwable) {
                    ++$result->stale;
                }
            }
        }
        return $result;
    }

    private function fingerprint(Throwable $exception): string
    {
        return 'v1:' . hash('sha256', "blackops.outbox.relay.failure.v1\0" . $exception::class);
    }

    private function backoffSeconds(int $attemptCount): int
    {
        $delay = $this->configuration->initialBackoffSeconds;
        $steps = max(0, $attemptCount - 1);
        for ($step = 0; $step < $steps && $delay < $this->configuration->maxBackoffSeconds; ++$step) {
            if ($delay > intdiv($this->configuration->maxBackoffSeconds, num2: 2)) {
                return $this->configuration->maxBackoffSeconds;
            }
            $delay *= 2;
        }

        return min($delay, $this->configuration->maxBackoffSeconds);
    }
}
