<?php

declare(strict_types=1);

namespace BlackOps\Internal\Http;

use BlackOps\Core\ActorContext;
use BlackOps\Core\Codec\OperationCodec;
use BlackOps\Core\EphemeralOutcome;
use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\DeferredAcknowledgement;
use BlackOps\Core\Execution\DeferredOperationMessage;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationEnvelope;
use BlackOps\Core\OperationResult;
use BlackOps\Core\OperationValue;
use BlackOps\Core\Registry\OperationRegistry;
use BlackOps\Core\TenantRef;
use BlackOps\Http\DeferredOperationAcceptor;
use BlackOps\Idempotency\IdempotencyKey;
use BlackOps\Internal\Execution\DeferredAcceptanceOrchestrator;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Registry\OperationMetadataResolver;
use BlackOps\Internal\Telemetry\TelemetryTracer;
use BlackOps\Telemetry\TelemetryContext;
use LogicException;

final readonly class DeferredHttpOperationAcceptor implements DeferredOperationAcceptor
{
    private OperationMetadataResolver $metadataResolver;

    public function __construct(
        OperationRegistry $registry,
        private ExecutionContextFactory $contexts,
        private OperationCodec $codec,
        private DeferredAcceptanceOrchestrator $orchestrator,
        ?OperationMetadataResolver $metadataResolver = null,
        private ?TelemetryTracer $telemetryTracer = null,
    ) {
        $this->metadataResolver = $metadataResolver ?? new OperationMetadataResolver($registry);
    }

    public function accepts(Operation $definition): bool
    {
        $metadata = $this->metadataResolver->resolve($definition);

        return (
            $metadata !== null
            && !is_a($metadata->outcome, EphemeralOutcome::class, allow_string: true)
            && $metadata->strategy === Deferred::class
        );
    }

    /** @mago-expect lint:excessive-parameter-list */
    public function accept(
        Operation $definition,
        OperationValue $value,
        ?ActorContext $actorContext = null,
        ?IdempotencyKey $idempotencyKey = null,
        ?TenantRef $tenant = null,
        ?TelemetryContext $telemetry = null,
    ): DeferredAcknowledgement|OperationResult {
        $metadata = $this->metadataResolver->resolve($definition) ?? throw new LogicException(
            'Deferred operation definition is not registered.',
        );

        if ($metadata->strategy !== Deferred::class) {
            throw new LogicException('Deferred HTTP acceptor requires the Deferred execution strategy.');
        }
        if (is_a($metadata->outcome, EphemeralOutcome::class, allow_string: true)) {
            throw new LogicException('Ephemeral operations cannot use deferred execution.');
        }

        if ($idempotencyKey !== null && $actorContext?->authorization() === null) {
            return OperationResult::rejected(\BlackOps\Core\Rejection\RejectionReason::businessRule(
                'idempotency_requires_authenticated_actor',
            ));
        }

        $context = $this->contexts->receive(
            actorContext: $actorContext,
            idempotencyKey: $idempotencyKey,
            tenant: $tenant,
            telemetry: $telemetry,
        );
        $strategy = new Deferred();
        $envelope = new OperationEnvelope($definition, $value, $context, $strategy);
        $span = $this->telemetryTracer?->operation($envelope, $metadata->typeId, TelemetryTracer::KIND_PRODUCER);
        try {
            $producer = $this->telemetryTracer?->currentContext();
            $context = $producer === null ? $context : $this->contexts->withTelemetry($context, $producer);
            $envelope = new OperationEnvelope($definition, $value, $context, $strategy);
            $encoded = $this->codec->encode($metadata, $value, $context);
            $result = $this->orchestrator->accept(
                new DeferredOperationMessage(
                    $context->operationId(),
                    $encoded->operationType(),
                    $encoded->schemaVersion(),
                    $encoded->encodedPayload(),
                    $encoded->encodedContext(),
                    $context->receivedAt(),
                    $context->tenant(),
                    $context->actorContext()?->origin(),
                ),
                $envelope,
                $metadata,
            );
            $span?->result($result instanceof OperationResult && $result->isRejected() ? 'rejected' : 'completed');
            return $result;
        } catch (\Throwable $failure) {
            $span?->fail($failure);
            throw $failure;
        } finally {
            $span?->end();
        }
    }
}
