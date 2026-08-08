<?php

declare(strict_types=1);

namespace BlackOps\Internal\ExecutionContext;

use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\AttemptContext;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CausationId;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\ScheduleContext;
use BlackOps\Core\TenantRef;
use BlackOps\Idempotency\IdempotencyKey;
use BlackOps\Internal\Identifier\IdentifierFactory;
use BlackOps\Telemetry\TelemetryContext;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use Psr\Clock\ClockInterface;

/**
 * ExecutionContextの生成と遷移を集約するInternal Factory。
 *
 * IdentifierFactoryとPSR-20 Clockを注入し、Root受信、Attempt開始、子Operation Context生成を
 * 行う。Reflection、Closure binding、非公開Property書換えを使用しない。生成後の改変は
 * 公開 `with...()` Methodではなく、新ExecutionContextを構築することで表現する。
 *
 * このClassはPHP Public APIではなく、Framework内部実装詳細である。
 */
final readonly class ExecutionContextFactory
{
    public function __construct(
        private IdentifierFactory $identifiers,
        private ClockInterface $clock,
    ) {}

    public function receive(
        ?DateTimeImmutable $deadline = null,
        ?ActorContext $actorContext = null,
        ?IdempotencyKey $idempotencyKey = null,
        ?TenantRef $tenant = null,
        ?TelemetryContext $telemetry = null,
    ): ExecutionContext {
        $operationId = $this->identifiers->newOperationId();
        $correlationId = CorrelationId::fromString($operationId->toString());

        return new ExecutionContext(
            $operationId,
            $this->clock->now(),
            $correlationId,
            null,
            null,
            $deadline,
            $actorContext,
            $idempotencyKey?->hash(),
            null,
            $tenant,
            $telemetry,
        );
    }

    /** @mago-expect lint:excessive-parameter-list */
    public function receiveScheduled(
        OperationId $operationId,
        DateTimeImmutable $receivedAt,
        ScheduleContext $schedule,
        ?ActorContext $actorContext = null,
        ?TenantRef $tenant = null,
        ?TelemetryContext $telemetry = null,
    ): ExecutionContext {
        return new ExecutionContext(
            $operationId,
            $receivedAt,
            CorrelationId::fromString($operationId->toString()),
            null,
            null,
            null,
            $actorContext,
            null,
            $schedule,
            $tenant,
            $telemetry,
        );
    }

    public function startAttempt(
        ExecutionContext $context,
        int $attemptNumber,
        ?ActorRef $executionActor = null,
    ): ExecutionContext {
        $deadline = $context->deadline();
        $startedAt = $this->clock->now();

        if ($deadline !== null && $startedAt >= $deadline) {
            throw new LogicException('Cannot start an attempt after the ExecutionContext deadline has been reached.');
        }

        $attempt = new AttemptContext($this->identifiers->newAttemptId(), $attemptNumber, $startedAt);

        return new ExecutionContext(
            $context->operationId(),
            $context->receivedAt(),
            $context->correlationId(),
            $context->causationId(),
            $attempt,
            $context->deadline(),
            $this->resolveActorContext($context->actorContext(), $executionActor),
            $context->idempotencyKeyHash(),
            $context->schedule(),
            $context->tenant(),
            $context->telemetry(),
        );
    }

    public function createChild(
        ExecutionContext $parent,
        ?DateTimeImmutable $deadline = null,
        ?ActorRef $executionActor = null,
    ): ExecutionContext {
        $resolvedDeadline = $this->resolveChildDeadline($parent, $deadline);
        $operationId = $this->identifiers->newOperationId();
        $causationId = CausationId::fromString($parent->operationId()->toString());

        return new ExecutionContext(
            $operationId,
            $this->clock->now(),
            $parent->correlationId(),
            $causationId,
            null,
            $resolvedDeadline,
            $this->resolveActorContext($parent->actorContext(), $executionActor),
            null,
            null,
            $parent->tenant(),
            $parent->telemetry(),
        );
    }

    private function resolveActorContext(?ActorContext $context, ?ActorRef $executionActor): ?ActorContext
    {
        if ($executionActor === null) {
            return $context;
        }

        return new ActorContext($context?->origin(), $context?->authorization(), $executionActor);
    }

    private function resolveChildDeadline(ExecutionContext $parent, ?DateTimeImmutable $deadline): ?DateTimeImmutable
    {
        $parentDeadline = $parent->deadline();

        if ($deadline === null) {
            return $parentDeadline;
        }

        if ($parentDeadline !== null && $deadline > $parentDeadline) {
            throw new InvalidArgumentException(
                'Child ExecutionContext deadline must not be later than the parent deadline.',
            );
        }

        return $deadline;
    }
}
