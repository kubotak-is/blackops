<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\Execution\OperationClaim;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Supervision\SupervisionAction;
use BlackOps\Journal\Data\AttemptFailedData;
use BlackOps\Journal\Data\AttemptRetryScheduledData;
use BlackOps\Journal\Data\OperationFailedData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\LifecycleState;
use DateTimeImmutable;
use LogicException;
use Throwable;

final readonly class DeferredFailureSupervisor
{
    public function __construct(
        private DeferredWorkerRuntimeServices $services,
        private DeferredWorkerRuntimeStorage $storage,
    ) {}

    public function record(
        OperationClaim $claim,
        OperationMetadata $metadata,
        OperationEnvelope $envelope,
        Throwable $exception,
    ): void {
        $this->storage->connection->transactional(function () use ($claim, $metadata, $envelope, $exception): void {
            $now = $this->storage->clock->now();
            $reservation = $this->storage->state->reserveFailed($claim, $now);
            $attempt = $envelope->context()->attempt();

            if ($attempt === null) {
                throw new LogicException('Deferred worker failure requires an attempt context.');
            }

            $decision = $this->services->supervision->decide($exception, $attempt);
            $retryable = $decision->action() === SupervisionAction::Retry;

            $this->storage->lifecycle->next(LifecycleState::Running, JournalEvent::AttemptFailed);
            $this->storage->journal->append($this->storage->records->attemptFailed(
                $envelope,
                $metadata,
                $reservation->sequence,
                new AttemptFailedData($exception::class, $exception->getMessage(), $retryable),
            ));

            match ($decision->action()) {
                SupervisionAction::Retry => $this->scheduleRetry(
                    $claim,
                    $metadata,
                    $envelope,
                    $decision->delayMilliseconds(),
                    $now,
                ),
                SupervisionAction::Fail, SupervisionAction::DeadLetter => $this->failOperation(
                    $claim,
                    $metadata,
                    $envelope,
                    $exception,
                ),
            };
        });
    }

    private function scheduleRetry(
        OperationClaim $claim,
        OperationMetadata $metadata,
        OperationEnvelope $envelope,
        int $delayMilliseconds,
        DateTimeImmutable $now,
    ): void {
        $attempt = $envelope->context()->attempt();

        if ($attempt === null) {
            throw new LogicException('Deferred worker retry scheduling requires an attempt context.');
        }

        $scheduledAt = $this->addMilliseconds($now, $delayMilliseconds);
        $reservation = $this->storage->state->reserveRetryScheduled($claim, $scheduledAt, $now);
        $this->storage->lifecycle->next(LifecycleState::Supervising, JournalEvent::AttemptRetryScheduled);
        $this->storage->journal->append($this->storage->records->attemptRetryScheduled(
            $envelope,
            $metadata,
            $reservation->sequence,
            new AttemptRetryScheduledData($attempt->id(), $attempt->number() + 1, $scheduledAt, $delayMilliseconds),
        ));
    }

    private function failOperation(
        OperationClaim $claim,
        OperationMetadata $metadata,
        OperationEnvelope $envelope,
        Throwable $exception,
    ): void {
        $reservation = $this->storage->state->reserveOperationFailed($claim, $this->storage->clock->now());
        $this->storage->lifecycle->next(LifecycleState::Supervising, JournalEvent::OperationFailed);
        $this->storage->journal->append($this->storage->records->operationFailed(
            $envelope,
            $metadata,
            $reservation->sequence,
            new OperationFailedData($exception::class, $exception->getMessage(), false),
        ));
    }

    private function addMilliseconds(DateTimeImmutable $time, int $milliseconds): DateTimeImmutable
    {
        $seconds = intdiv($milliseconds, num2: 1_000);
        $microseconds = ($milliseconds % 1_000) * 1_000;

        return $time->modify('+' . $seconds . ' seconds')->modify('+' . $microseconds . ' microseconds');
    }
}
