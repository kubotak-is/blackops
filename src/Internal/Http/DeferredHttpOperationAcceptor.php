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
use BlackOps\Http\DeferredOperationAcceptor;
use BlackOps\Internal\Execution\DeferredAcceptanceOrchestrator;
use BlackOps\Internal\ExecutionContext\ExecutionContextFactory;
use BlackOps\Internal\Registry\OperationMetadataResolver;
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

    public function accept(
        Operation $definition,
        OperationValue $value,
        ?ActorContext $actorContext = null,
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

        $context = $this->contexts->receive(actorContext: $actorContext);
        $strategy = new Deferred();
        $envelope = new OperationEnvelope($definition, $value, $context, $strategy);
        $encoded = $this->codec->encode($metadata, $value, $context);

        return $this->orchestrator->accept(
            new DeferredOperationMessage(
                $context->operationId(),
                $encoded->operationType(),
                $encoded->schemaVersion(),
                $encoded->encodedPayload(),
                $encoded->encodedContext(),
                $context->receivedAt(),
            ),
            $envelope,
            $metadata,
        );
    }
}
