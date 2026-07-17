<?php

declare(strict_types=1);

namespace BlackOps\Internal\Execution;

use BlackOps\Core\Exception\DeferredTransportException;
use BlackOps\Core\Exception\OperationRejectedException;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\OperationClaim;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Internal\Application\ApplicationDatabaseConnectionLifecycle;
use BlackOps\Journal\JournalEvent;
use BlackOps\Journal\LifecycleState;
use BlackOps\Outcome\OutcomeRecord;
use LogicException;
use ReflectionClass;
use Throwable;

/**
 * Keeps claim fencing, supervision, and transactional terminal persistence in one attempt lifecycle.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 */
final readonly class DeferredWorkerRuntime implements DeferredClaimRuntime
{
    public function __construct(
        private DeferredWorkerRuntimeServices $services,
        private DeferredWorkerRuntimeStorage $storage,
        private ClaimExecutionGuard $guard = new DirectClaimExecutionGuard(),
        private HandlerInvoker $invoker = new HandlerInvoker(),
        private ?ApplicationDatabaseConnectionLifecycle $connections = null,
    ) {}

    public function run(OperationClaim $claim): OperationResult
    {
        $result = null;
        $failure = null;
        $prepared = false;

        try {
            $this->connections?->prepare();
            $prepared = true;
            $result = $this->runAttempt($claim);
        } catch (Throwable $exception) {
            $failure = $exception;
        }

        try {
            if ($failure === null) {
                $this->connections?->finishSuccessfulInvocation();
            }

            if ($failure !== null && $prepared) {
                $this->connections?->finishFailedInvocation();
            }
        } catch (Throwable $exception) {
            $failure ??= $exception;
        }

        if ($failure !== null) {
            throw $failure;
        }

        if (!$result instanceof OperationResult) {
            throw new LogicException('Deferred worker did not produce an operation result.');
        }

        return $result;
    }

    /** @mago-expect lint:halstead */
    private function runAttempt(OperationClaim $claim): OperationResult
    {
        $metadata = $this->metadata($claim);
        $handler = $this->services->handlers->resolve($metadata->handler);
        $definition = $this->definition($metadata, $handler);
        $value = $this->value($metadata, $claim);
        $envelope = $this->startAttempt($claim, $metadata, $definition, $value);
        try {
            $result = $this->execute($claim, $envelope, $handler, $metadata, $metadata->typeId);
        } catch (HandlerInvocationFailedException $exception) {
            $this->fail($claim, $metadata, $envelope, $exception->failure);

            throw new SupervisedHandlerFailureException(
                'Deferred handler failure was recorded by supervision.',
                previous: $exception->failure,
            );
        }

        if ($result->isCompleted()) {
            if (
                $metadata->transactionConnection === null
                || $this->storage->transactions === null
                || !$this->storage->transactions->sharesFrameworkConnection($metadata)
            ) {
                $this->complete($claim, $metadata, $envelope, $result);
            }

            return $result;
        }

        $this->reject($claim, $metadata, $envelope, $result);

        return $result;
    }

    private function execute(
        OperationClaim $claim,
        OperationEnvelope $envelope,
        object $handler,
        OperationMetadata $metadata,
        string $operationTypeId,
    ): OperationResult {
        return $this->guard->run($claim, fn(): OperationResult => $this->storage->scope->run(
            $envelope,
            function () use ($claim, $handler, $envelope, $metadata): OperationResult {
                try {
                    $rejection = $this->services->authorization->evaluate($metadata, $envelope);
                    if ($rejection !== null) {
                        return OperationResult::rejected($rejection, $envelope->id());
                    }

                    $invoke = function () use ($metadata, $handler, $envelope): OperationResult {
                        try {
                            return $this->invoker->invoke($metadata, $handler, $envelope);
                        } catch (OperationRejectedException $rejected) {
                            return OperationResult::rejected($rejected->reason(), $envelope->id());
                        }
                    };

                    if ($metadata->transactionConnection === null) {
                        return $invoke();
                    }

                    if ($this->storage->transactions === null) {
                        throw new LogicException('Operation transaction coordinator is unavailable.');
                    }

                    return $this->storage->transactions->execute(
                        $metadata,
                        $invoke,
                        fn(OperationResult $result): mixed => $this->completeInCurrentTransaction(
                            $claim,
                            $metadata,
                            $envelope,
                            $result,
                        ),
                    );
                } catch (WorkerExecutionInterruptedException $exception) {
                    throw $exception;
                } catch (Throwable $exception) {
                    throw new HandlerInvocationFailedException($exception);
                }
            },
            $operationTypeId,
        ));
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
                    $this->services->contexts->startAttempt(
                        $context,
                        $reservation->attemptNumber,
                        $this->services->executionActor,
                    ),
                    new Deferred(),
                );

                $attempt = $envelope->context()->attempt();

                if ($attempt === null) {
                    throw new LogicException('Deferred worker attempt context was not created.');
                }

                $this->storage->state->recordCurrentAttempt($claim, $attempt, $this->storage->clock->now());
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
            $this->completeInCurrentTransaction($claim, $metadata, $envelope, $result);
        });
    }

    private function completeInCurrentTransaction(
        OperationClaim $claim,
        OperationMetadata $metadata,
        OperationEnvelope $envelope,
        OperationResult $result,
    ): void {
        $completedAt = $this->storage->clock->now();
        $reservation = $this->storage->state->reserveCompleted($claim, $completedAt);
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
        $this->storage->outcomes->save(
            new OutcomeRecord($claim->message()->operationId(), $result->outcome(), $completedAt),
        );
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

    private function fail(
        OperationClaim $claim,
        OperationMetadata $metadata,
        OperationEnvelope $envelope,
        Throwable $exception,
    ): void {
        new DeferredFailureSupervisor($this->services, $this->storage)->record(
            $claim,
            $metadata,
            $envelope,
            $exception,
        );
    }

    private function metadata(OperationClaim $claim): OperationMetadata
    {
        $metadata = $this->services->registry->findByTypeId($claim->message()->operationType());

        if ($metadata === null || $metadata->strategy !== Deferred::class) {
            throw new LogicException('Deferred worker requires registered deferred operation metadata.');
        }

        return $metadata;
    }

    private function definition(OperationMetadata $metadata, object $handler): Operation
    {
        if (strcmp($metadata->definition, $metadata->handler) === 0) {
            if (!$handler instanceof Operation) {
                throw new LogicException('Self-handled service must implement Operation.');
            }

            return $handler;
        }

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
