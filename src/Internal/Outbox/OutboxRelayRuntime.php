<?php

declare(strict_types=1);

namespace BlackOps\Internal\Outbox;

use BlackOps\Core\Execution\OperationSender;
use BlackOps\Internal\Telemetry\TelemetryMetrics;
use BlackOps\Internal\Telemetry\TelemetryTracer;
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
        private ?TelemetryTracer $telemetry = null,
        private ?TelemetryMetrics $metrics = null,
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

    private function processBatch(DateTimeImmutable $now): OutboxRelayResult
    {
        $span = $this->telemetry?->start('blackops.outbox.relay', attributes: [
            'blackops.runtime.kind' => 'outbox_relay',
        ]);
        $metric = $this->metrics?->relayScope();
        try {
            $result = $this->processBatchInSpan($now);
            $span?->result($this->resultCode($result));
            $metric?->result($this->resultCode($result));
            return $result;
        } catch (Throwable $failure) {
            $span?->fail($failure);
            $metric?->fail();
            throw $failure;
        } finally {
            $span?->end();
            $metric?->end();
        }
    }

    private function resultCode(OutboxRelayResult $result): string
    {
        return match (true) {
            $result->deadLettered > 0 => 'dead_lettered',
            $result->retried > 0 => 'retry_scheduled',
            default => 'completed',
        };
    }

    private function processBatchInSpan(DateTimeImmutable $now): OutboxRelayResult
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
                $this->metrics?->relayRecord('completed');
            } catch (Throwable $exception) {
                $fingerprint = $this->fingerprint($exception);
                try {
                    if ($claim->attemptCount >= $this->configuration->maxAttempts) {
                        $this->store->moveToDeadLetter($claim, $fingerprint);
                        ++$result->deadLettered;
                        $this->metrics?->relayRecord('dead_lettered');
                    } else {
                        $delay = $this->backoffSeconds($claim->attemptCount);
                        $this->store->scheduleRetry(
                            $claim,
                            $this->clock->now()->modify('+' . $delay . ' seconds'),
                            $fingerprint,
                        );
                        ++$result->retried;
                        $this->metrics?->relayRecord('retry_scheduled');
                    }
                } catch (Throwable) {
                    ++$result->stale;
                    $this->metrics?->relayRecord('failed');
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
