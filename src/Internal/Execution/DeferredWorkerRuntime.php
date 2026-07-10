<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\OperationClaim;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\LifecycleState;
use LogicException;
use ReflectionClass;
use Throwable;

final readonly class DeferredWorkerRuntime
{
    public function __construct(
        private DeferredWorkerRuntimeServices $services,
        private DeferredWorkerRuntimeStorage $storage,
    ) {}

    public function run(OperationClaim $claim): OperationResult
    {
        $metadata = $this->metadata($claim);
        $definition = $this->definition($metadata);
        $value = $this->value($metadata, $claim);
        $envelope = $this->startAttempt($claim, $metadata, $definition, $value);
        $handler = $this->services->handlers->resolve($metadata->handler);
        $result = $this->storage->scope->run(
            $envelope,
            static fn(): OperationResult => $handler->handle($envelope),
            $metadata->typeId,
        );

        if ($result->isCompleted()) {
            $this->complete($claim, $metadata, $envelope, $result);

            return $result;
        }

        $this->reject($claim, $metadata, $envelope, $result);

        return $result;
    }

    private function startAttempt(
        OperationClaim $claim,
        OperationMetadata $metadata,
        Operation $definition,
        OperationValue $value,
    ): OperationEnvelope {
        try {
            return $this->storage->connection->transactional(function () use (
                $claim,
                $metadata,
                $definition,
                $value,
            ): OperationEnvelope {
                $context = $this->services->codec->decodeContext(
                    $claim->message()->schemaVersion(),
                    $claim->message()->encodedContext(),
                );
                $reservation = $this->storage->state->reserveAttemptStarted($claim, $this->storage->clock->now());
                $envelope = new OperationEnvelope(
                    $definition,
                    $value,
                    $this->services->contexts->startAttempt($context, $reservation->attemptNumber),
                    new Deferred(),
                );

                $this->storage->lifecycle->next(LifecycleState::Accepted, JournalEvent::AttemptStarted);
                $this->storage->journal->append($this->storage->records->attemptStarted(
                    $envelope,
                    $metadata,
                    $reservation->sequence,
                ));

                return $envelope;
            });
        } catch (Throwable $exception) {
            if ($exception instanceof DeferredTransportException) {
                throw $exception;
            }

            throw new DeferredTransportException('Failed to start deferred operation attempt.', previous: $exception);
        }
    }

    private function complete(
        OperationClaim $claim,
        OperationMetadata $metadata,
        OperationEnvelope $envelope,
        OperationResult $result,
    ): void {
        $this->storage->connection->transactional(function () use ($claim, $metadata, $envelope, $result): void {
            $reservation = $this->storage->state->reserveCompleted($claim, $this->storage->clock->now());
            $finalizing = $this->storage->lifecycle->next(LifecycleState::Running, JournalEvent::AttemptSucceeded);
            $this->storage->lifecycle->next($finalizing, JournalEvent::OperationCompleted);
            $this->storage->journal->append($this->storage->records->attemptSucceeded(
                $envelope,
                $metadata,
                $reservation->attemptSucceededSequence,
            ));
            $this->storage->journal->append($this->storage->records->operationCompleted(
                $envelope,
                $metadata,
                $reservation->operationCompletedSequence,
                $result->outcome(),
            ));
        });
    }

    private function reject(
        OperationClaim $claim,
        OperationMetadata $metadata,
        OperationEnvelope $envelope,
        OperationResult $result,
    ): void {
        $this->storage->connection->transactional(function () use ($claim, $metadata, $envelope, $result): void {
            $reservation = $this->storage->state->reserveRejected($claim, $this->storage->clock->now());
            $this->storage->lifecycle->next(LifecycleState::Running, JournalEvent::OperationRejected);
            $this->storage->journal->append($this->storage->records->operationRejected(
                $envelope,
                $metadata,
                $reservation->sequence,
                $result->rejectionReason(),
            ));
        });
    }

    private function metadata(OperationClaim $claim): OperationMetadata
    {
        $metadata = $this->services->registry->findByTypeId($claim->message()->operationType());

        if ($metadata === null || $metadata->strategy !== Deferred::class) {
            throw new LogicException('Deferred worker requires registered deferred operation metadata.');
        }

        return $metadata;
    }

    private function definition(OperationMetadata $metadata): Operation
    {
        $class = $metadata->definition;
        $reflection = new ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new LogicException('Deferred worker operation definition must be instantiable.');
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
}
