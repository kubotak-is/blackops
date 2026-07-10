<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\OperationClaim;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\Supervision\SupervisionAction;
use BlackOps\Journal\Data\AttemptFailedData;
use BlackOps\Journal\Data\AttemptRetryScheduledData;
use BlackOps\Journal\Data\OperationDeadLetteredData;
use BlackOps\Journal\Data\OperationFailedData;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\LifecycleState;
use BlackOps\Transport\PostgreSql\PostgreSqlLeaseExpiredReservation;
use DateTimeImmutable;
use LogicException;
use ReflectionClass;

final readonly class DeferredLeaseExpiredRecovery
{
    public function __construct(
        private DeferredWorkerRuntimeServices $services,
        private DeferredWorkerRuntimeStorage $storage,
    ) {}

    public function recoverOne(DateTimeImmutable $expiredAt): bool
    {
        return $this->storage->connection->transactional(function () use ($expiredAt): bool {
            $reservation = $this->storage->state->reserveLeaseExpired($expiredAt);

            if ($reservation === null) {
                return false;
            }

            $metadata = $this->metadata($reservation->claim);
            $envelope = $this->envelope($reservation, $metadata);
            $exception = new LeaseExpiredException();
            $decision = $this->services->supervision->decide($exception, $reservation->attempt);
            $retryable = $decision->action() === SupervisionAction::Retry;

            $this->storage->lifecycle->next(LifecycleState::Running, JournalEvent::AttemptFailed);
            $this->storage->journal->append($this->storage->records->attemptFailed(
                $envelope,
                $metadata,
                $reservation->sequence,
                new AttemptFailedData('lease_expired', $exception->getMessage(), $retryable),
            ));

            match ($decision->action()) {
                SupervisionAction::Retry => $this->scheduleRetry(
                    $reservation->claim,
                    $metadata,
                    $envelope,
                    $decision->delayMilliseconds(),
                    $expiredAt,
                ),
                SupervisionAction::Fail => $this->failOperation($reservation->claim, $metadata, $envelope, $exception),
                SupervisionAction::DeadLetter => $this->deadLetterOperation(
                    $reservation->claim,
                    $metadata,
                    $envelope,
                    $exception,
                    $expiredAt,
                ),
            };

            return true;
        });
    }

    private function envelope(
        PostgreSqlLeaseExpiredReservation $reservation,
        OperationMetadata $metadata,
    ): OperationEnvelope {
        $context = $this->services->codec->decodeContext(
            $reservation->claim->message()->schemaVersion(),
            $reservation->claim->message()->encodedContext(),
        );

        return new OperationEnvelope(
            $this->definition($metadata),
            $this->value($metadata, $reservation->claim),
            new ExecutionContext(
                $context->operationId(),
                $context->receivedAt(),
                $context->correlationId(),
                $context->causationId(),
                $reservation->attempt,
                $context->deadline(),
            ),
            new Deferred(),
        );
    }

    private function scheduleRetry(
        OperationClaim $claim,
        OperationMetadata $metadata,
        OperationEnvelope $envelope,
        int $delayMilliseconds,
        DateTimeImmutable $expiredAt,
    ): void {
        $scheduledAt = $this->addMilliseconds($expiredAt, $delayMilliseconds);
        $attempt = $envelope->context()->attempt();

        if ($attempt === null) {
            throw new LogicException('Lease expired recovery requires an attempt context.');
        }

        $reservation = $this->storage->state->reserveRetryScheduled($claim, $scheduledAt, $expiredAt);
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
        LeaseExpiredException $exception,
    ): void {
        $reservation = $this->storage->state->reserveOperationFailed($claim, $this->storage->clock->now());
        $this->storage->lifecycle->next(LifecycleState::Supervising, JournalEvent::OperationFailed);
        $this->storage->journal->append(
            $this->storage
                ->records
                ->terminal()
                ->operationFailed(
                    $envelope,
                    $metadata,
                    $reservation->sequence,
                    new OperationFailedData('lease_expired', $exception->getMessage(), false),
                ),
        );
    }

    private function deadLetterOperation(
        OperationClaim $claim,
        OperationMetadata $metadata,
        OperationEnvelope $envelope,
        LeaseExpiredException $exception,
        DateTimeImmutable $expiredAt,
    ): void {
        $attempt = $envelope->context()->attempt();
        $data = new OperationDeadLetteredData(
            $attempt?->id(),
            $attempt?->number(),
            'lease_expired',
            $exception->getMessage(),
            $expiredAt,
        );
        $reservation = $this->storage->state->reserveDeadLettered($claim, $data, $expiredAt);
        $this->storage->lifecycle->next(LifecycleState::Supervising, JournalEvent::OperationDeadLettered);
        $this->storage->journal->append(
            $this->storage
                ->records
                ->terminal()
                ->operationDeadLettered($envelope, $metadata, $reservation->sequence, $data),
        );
    }

    private function metadata(OperationClaim $claim): OperationMetadata
    {
        $metadata = $this->services->registry->findByTypeId($claim->message()->operationType());

        if ($metadata === null || $metadata->strategy !== Deferred::class) {
            throw new LogicException('Lease expired recovery requires registered deferred operation metadata.');
        }

        return $metadata;
    }

    private function definition(OperationMetadata $metadata): Operation
    {
        $reflection = new ReflectionClass($metadata->definition);

        if (!$reflection->isInstantiable()) {
            throw new LogicException('Lease expired recovery operation definition must be instantiable.');
        }

        return $reflection->newInstance();
    }

    private function value(OperationMetadata $metadata, OperationClaim $claim): OperationValue
    {
        return $this->services->codec->decodeValue(
            $metadata,
            $claim->message()->schemaVersion(),
            $claim->message()->encodedPayload(),
        );
    }

    private function addMilliseconds(DateTimeImmutable $time, int $milliseconds): DateTimeImmutable
    {
        $seconds = intdiv($milliseconds, num2: 1_000);
        $microseconds = ($milliseconds % 1_000) * 1_000;

        return $time->modify('+' . $seconds . ' seconds')->modify('+' . $microseconds . ' microseconds');
    }
}
