<?php

declare(strict_types=1);

namespace BlackOps\Internal\Console;

use BlackOps\Console\ConsoleActorProvider;
use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\EmptyOutcome;
use BlackOps\Core\EphemeralOutcome;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationResult;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Core\Rejection\RejectionCategory;
use BlackOps\Core\Time\TimeCodec;
use BlackOps\Internal\Application\ApplicationOperationInvocationLifecycle;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Execution\HandlerResolver;
use BlackOps\Internal\Execution\InlineDispatcher;
use BlackOps\Internal\Execution\OperationExecutionFailed;
use BlackOps\Internal\Http\DeferredHttpOperationAcceptor;
use BlackOps\Internal\Logging\ExecutionScopedLogger;
use BlackOps\Internal\Logging\FrameworkOperationFailureReporter;
use BlackOps\Internal\Registry\OperationDefinitionFactory;
use BlackOps\Outcome\Internal\StructuredOutcomeNormalizer;
use Psr\Container\ContainerInterface;
use Throwable;

/** @mago-expect lint:cyclomatic-complexity */
final readonly class OperationConsoleRuntime
{
    /** @mago-expect lint:excessive-parameter-list */
    public function __construct(
        private OperationRegistry $operations,
        private ContainerInterface $container,
        private InlineDispatcher $inline,
        private DeferredHttpOperationAcceptor $deferred,
        private ApplicationOperationInvocationLifecycle $lifecycle,
        private ExecutionScopeProvider $scope,
        private ExecutionScopedLogger $logger,
        private OperationConsoleValueBinder $binder = new OperationConsoleValueBinder(),
        private StructuredOutcomeNormalizer $outcomes = new StructuredOutcomeNormalizer(),
        private TimeCodec $time = new TimeCodec(),
    ) {}

    /** @param array<string, mixed> $values */
    public function invoke(OperationConsoleCommandMetadata $command, array $values): OperationConsoleInvocationResult
    {
        try {
            return $this->lifecycle->run(
                fn(): OperationConsoleInvocationResult => $this->execute($command, $values),
                static fn(OperationConsoleInvocationResult $result): bool => $result->payload['status'] === 'error',
            );
        } catch (OperationExecutionFailed $exception) {
            new FrameworkOperationFailureReporter($this->logger, $this->scope)->report($exception);

            return $this->internal($exception->operationId()->toString());
        } catch (Throwable $exception) {
            $this->logger->frameworkSystemError($exception::class);

            return $this->internal();
        }
    }

    /** @param array<string, mixed> $values */
    private function execute(OperationConsoleCommandMetadata $command, array $values): OperationConsoleInvocationResult
    {
        $definition = $this->definition($command);
        $bound = $this->binder->bind($command, $values);
        if (is_array($bound)) {
            $operationId = $this->inline->rejectBinding($definition, $bound);

            return $this->validationResult($operationId->toString(), $bound);
        }
        $violations = $this->inline->validate($bound);
        if ($violations !== []) {
            $operationId = $this->inline->rejectValue($definition, $bound, $violations);

            return $this->validationResult($operationId->toString(), $violations);
        }

        $actor = $this->actorContext();
        $executed = $command->strategy === Deferred::class
            ? $this->deferred->accept($definition, $bound, $actor)
            : $this->inline->dispatch($definition, $bound, $actor);

        return $this->result($executed);
    }

    private function definition(OperationConsoleCommandMetadata $command): Operation
    {
        $metadata = $this->operations->findByTypeId($command->typeId) ?? throw new \LogicException(
            'Operation console definition is unavailable.',
        );
        if (is_a($metadata->outcome, EphemeralOutcome::class, allow_string: true)) {
            throw new \LogicException('Ephemeral operations are unavailable through the console runtime.');
        }

        return new OperationDefinitionFactory()->fromMetadata(
            $metadata,
            new HandlerResolver($this->container)->resolve(...),
        );
    }

    private function actorContext(): ActorContext
    {
        $actor = null;
        if ($this->container->has(ConsoleActorProvider::class)) {
            /** @var mixed $provider */
            $provider = $this->container->get(ConsoleActorProvider::class);
            if (!$provider instanceof ConsoleActorProvider) {
                throw new \LogicException('Console actor provider has an invalid runtime type.');
            }
            $actor = $provider->actor();
        }

        return new ActorContext($actor, $actor, new ActorRef('console-runtime', 'system'));
    }

    private function result(DeferredAcknowledgement|OperationResult $result): OperationConsoleInvocationResult
    {
        if ($result instanceof DeferredAcknowledgement) {
            return new OperationConsoleInvocationResult([
                'schemaVersion' => 1,
                'status' => 'accepted',
                'operationId' => $result->operationId()->toString(),
                'acceptedAt' => $this->time->format($result->acceptedAt()),
            ], 0);
        }
        if ($result->isRejected()) {
            $reason = $result->rejectionReason();
            $id = $result->operationId();

            return new OperationConsoleInvocationResult(
                [
                    'schemaVersion' => 1,
                    'status' => 'rejected',
                    ...($id === null ? [] : ['operationId' => $id->toString()]),
                    'category' => $reason->category()->value,
                    'code' => $reason->code(),
                    'violations' => $this->violations($reason->violations()),
                ],
                $reason->category() === RejectionCategory::Validation ? 2 : 1,
            );
        }

        $outcome = $result->outcome();
        $normalized = $outcome instanceof EmptyOutcome ? [] : $this->outcomes->normalize($outcome);

        return new OperationConsoleInvocationResult([
            'schemaVersion' => 1,
            'status' => 'completed',
            'outcome' => $normalized === [] ? new \stdClass() : $normalized,
        ], 0);
    }

    /** @param list<\BlackOps\Core\Validation\Violation> $violations */
    private function validationResult(string $operationId, array $violations): OperationConsoleInvocationResult
    {
        return new OperationConsoleInvocationResult([
            'schemaVersion' => 1,
            'status' => 'rejected',
            'operationId' => $operationId,
            'category' => 'validation',
            'code' => 'validation.failed',
            'violations' => $this->violations($violations),
        ], 2);
    }

    /** @param list<\BlackOps\Core\Validation\Violation> $violations @return list<array{field: string, rule: string, code: string}> */
    private function violations(array $violations): array
    {
        return array_map(static fn($violation): array => [
            'field' => $violation->field,
            'rule' => $violation->rule,
            'code' => $violation->code,
        ], $violations);
    }

    private function internal(?string $operationId = null): OperationConsoleInvocationResult
    {
        return new OperationConsoleInvocationResult([
            'schemaVersion' => 1,
            'status' => 'error',
            'code' => 'internal_error',
            ...($operationId === null ? [] : ['operationId' => $operationId]),
        ], 1);
    }
}
