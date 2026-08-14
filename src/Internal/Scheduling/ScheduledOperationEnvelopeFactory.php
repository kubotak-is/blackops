<?php

declare(strict_types=1);

namespace BlackOps\Internal\Scheduling;

use BlackOps\Core\ActorContext;
use BlackOps\Core\Execution\ExecutionStrategy;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationMetadata;
use BlackOps\Core\ScheduleContext;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Scheduling\ScheduledActorProvider;
use BlackOps\Scheduling\ScheduledTenantProvider;
use LogicException;
use ReflectionClass;

final readonly class ScheduledOperationEnvelopeFactory
{
    public function __construct(
        private ExecutionContextFactory $contexts,
        private ?ScheduledActorProvider $actors = null,
        private ?ScheduledTenantProvider $tenants = null,
    ) {}

    public function create(
        OperationMetadata $metadata,
        Operation $definition,
        OperationValue $value,
        ScheduleOccurrence $occurrence,
        ExecutionStrategy $strategy,
    ): OperationEnvelope {
        $schedule = $metadata->schedule;
        $operationId = $occurrence->operationId;
        if ($schedule === null || $operationId === null) {
            throw new LogicException('Scheduled envelope requires a claimed occurrence and schedule metadata.');
        }
        if ($occurrence->scheduleName !== $schedule->name) {
            throw new LogicException('Scheduled occurrence does not match operation schedule metadata.');
        }
        if (
            !is_a($definition::class, $metadata->definition, allow_string: true)
            || !is_a($value::class, $metadata->value, allow_string: true)
        ) {
            throw new LogicException('Scheduled envelope metadata does not match its operation objects.');
        }

        $context = new ScheduleContext($schedule->name, $occurrence->scheduledAt, $schedule->timezone);
        try {
            $actor = $this->actors?->actor($context);
        } catch (\Throwable $exception) {
            throw new LogicException('Scheduled actor could not be resolved.', previous: $exception);
        }
        if ($metadata->authorizationPolicy !== null && $this->actors === null) {
            throw new LogicException('Authorized scheduled operation requires an actor provider.');
        }
        $actorContext = new ActorContext($actor, $actor, ScheduledRuntimeActor::ref());
        try {
            $tenant = $this->tenants?->tenant($context);
        } catch (\Throwable $exception) {
            throw new LogicException('Scheduled tenant could not be resolved.', previous: $exception);
        }

        return new OperationEnvelope(
            $definition,
            $value,
            $this->contexts->receiveScheduled($operationId, $occurrence->evaluatedAt, $context, $actorContext, $tenant),
            $strategy,
        );
    }

    public function value(OperationMetadata $metadata): OperationValue
    {
        $valueClass = $metadata->value;
        try {
            $reflection = new ReflectionClass($valueClass);
            if (!$reflection->isInstantiable()) {
                throw new LogicException('Scheduled operation value must be instantiable.');
            }
            $value = $reflection->newInstance();
        } catch (\Throwable $exception) {
            throw new LogicException('Scheduled operation value could not be constructed.', previous: $exception);
        }

        return $value;
    }
}
